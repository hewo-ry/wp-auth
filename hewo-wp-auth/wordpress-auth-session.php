<?php

namespace Hewo\WpAuth;

use Exception;
use Facile\OpenIDClient\Session\AuthSessionInterface;

final class WordPressAuthSession implements AuthSessionInterface {

    private const COOKIE_NAME = 'hewo_wp_auth_session_id';
    private const TRANSIENT_PREFIX = 'hewo_wp_auth_session_';

    private string $sessionKey;
    private array $data = [];

    public function __construct() {
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $this->sessionKey = sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] );
            $this->load();
        } else {
            $this->sessionKey = wp_generate_password( 32, false );
            if ( ! headers_sent() )
                setcookie( self::COOKIE_NAME, $this->sessionKey, [
                    'expires' => time() + 900,
                    'path' => COOKIEPATH,
                    'domain' => COOKIE_DOMAIN,
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ] );
        }
    }

    private function load(): void {
        $transientName = self::TRANSIENT_PREFIX . $this->sessionKey;
        $savedData = get_transient( $transientName );
        if ( is_array( $savedData ) )
            $this->data = $savedData;
    }

    public function save(): void {
        $transientName = self::TRANSIENT_PREFIX . $this->sessionKey;
        set_transient( $transientName, $this->data, 15 * MINUTE_IN_SECONDS );
    }

    public function clear(): void {
        $this->data = [];
        delete_transient( self::TRANSIENT_PREFIX . $this->sessionKey );

        if ( ! headers_sent() )
            setcookie( self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }

    public function getState(): ?string {
        if ( ! array_key_exists( 'state', $this->data ) )
            throw new Exception( 'Failed to load state from auth session.' );

        return $this->data['state'];
    }

    public function setState( ?string $state ): void {
        $this->data['state'] = $state;
        $this->save();
    }

    public function getNonce(): ?string {
        if ( ! array_key_exists( 'nonce', $this->data ) )
            throw new Exception( 'Failed to load nonce from auth session.' );

        return $this->data['nonce'];
    }

    public function setNonce( ?string $nonce ): void {
        $this->data['nonce'] = $nonce;
        $this->save();
    }

    public function getCodeVerifier(): ?string {
        return $this->data['code_verifier'] ?? null;
    }

    public function setCodeVerifier( ?string $codeVerifier ): void {
        $this->data['code_verifier'] = $codeVerifier;
        $this->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustoms(): array {
        return $this->data['customs'] ?? [];
    }

    /**
     * @param array<string, mixed> $customs
     */
    public function setCustoms( array $customs ): void {
        $this->data['customs'] = $customs;
        $this->save();
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return static
     */
    public static function fromArray( array $array ): self {
        $session = new static();
        $session->setState( $array['state'] ?? null );
        $session->setNonce( $array['nonce'] ?? null );
        $session->setCodeVerifier( $array['code_verifier'] ?? null );
        $session->setCustoms( $array['customs'] ?? [] );

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return array_filter( [
            'state' => $this->getState(),
            'nonce' => $this->getNonce(),
            'code_verifier' => $this->getCodeVerifier(),
            'customs' => $this->getCustoms(),
        ] );
    }
}
