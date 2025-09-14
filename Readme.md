# NEW PROJECT INSTALLATION

## Prerequisits:
    PHP Version 8.3.14
    cURL support	enabled
    OpenSSL support	enabled
    openssl.cafile	C:/wamp64/bin/php/php8.3.14/extras/ssl/openssl.pem

### API
    // db connection in .env
    composer install
    php bin/console doctrine:migrations:migrate

### Android
    Open project in android studio

### HUB
    // db connection in .env
    composer install
    npm install
    npm run build
    php bin/console doctrine:migrations:migrate

### DEMO
    // db connection in .env
    composer install
    php bin/console doctrine:migrations:migrate
    
## After installation first Step:
    1. Android Handy registration
    2. Set in the HUB 
       .env ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION=1
    3. Open HUB instance registration

