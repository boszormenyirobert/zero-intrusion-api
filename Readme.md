# NEW PROJECT INSTALLATION

## Prerequisits:
    PHP Version 8.3.14
    cURL support	enabled
    OpenSSL support	enabled
    openssl.cafile	C:/wamp64/bin/php/php8.3.14/extras/ssl/openssl.pem

### API
    // db connection in .env
    composer install
    php bin/console doctrine:database:create --if-not-exists
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
    0. Open the api log file
    1. Android Handy registration
    2. Set in the HUB 
       .env ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION=1
    3. !!! Take from the log the public id and copy his publicId, replace on frontend HUB .env  by INSTALLATION_PUBLIC_ID=   


    3. Create public and private key in the 
        /config/jwt
        
        openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:ZeroIntrusionLockAndLayeredEncryption -pkeyopt rsa_keygen_bits:4096
        openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:ZeroIntrusionLockAndLayeredEncryption

            chmod 644 config/jwt/public.pem      chown www-data:www-data
            chmod 600 config/jwt/private.pem     chown www-data:www-data
    4. Open HUB instance registration
    2. Set in the HUB 
       .env ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION=1       


