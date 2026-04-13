# Hewo WP Auth

## Kehittäminen

Asenna kirjastot:

```shell
docker run --rm -it -v "$(pwd):/app" composer/composer install
```

### Sovelluksen ajaminen Dev Containerilla

1) Luo salaisuudet

```shell
mkdir -p .devcontainer/secrets
pwgen -s 64 1 | tr -d '\n' > .devcontainer/secrets/db-root-password
pwgen -s 64 1 | tr -d '\n' > .devcontainer/secrets/db-password
cat > .devcontainer/secrets/client-secret
<paste client secret>
^D
```

2) Avaa projekti vscodessa
3) Varmista että suositellut laajennukset ovat aktiivisena
4) Avaa projekti containerissa (ilmoituksesta)

Debug logeja saat seurattua esim.:

```shell
docker exec -it hewo-wp-auth tail -f /var/www/html/wp-content/debug.log
```
