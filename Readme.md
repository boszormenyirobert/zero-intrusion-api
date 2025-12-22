# NEW PROJECT INSTALLATION

## Prerequisits:
    PHP Version 8.3.14
    cURL support	enabled
    OpenSSL support	enabled
    openssl.cafile	C:/wamp64/bin/php/php8.3.14/extras/ssl/openssl.pem
    Ubuntu:
        sudo mkdir -p /etc/ssl/certs && \
        sudo curl -o /etc/ssl/certs/cacert.pem https://curl.se/ca/cacert.pem && \
        sudo sed -i '/openssl.cafile/d' /etc/php/8.3/cli/php.ini && \
        sudo sed -i '/curl.cainfo/d' /etc/php/8.3/cli/php.ini && \
        echo "openssl.cafile=/etc/ssl/certs/cacert.pem" | sudo tee -a /etc/php/8.3/cli/php.ini && \
        echo "curl.cainfo=/etc/ssl/certs/cacert.pem" | sudo tee -a /etc/php/8.3/cli/php.ini && \
        sudo sed -i '/openssl.cafile/d' /etc/php/8.3/apache2/php.ini && \
        sudo sed -i '/curl.cainfo/d' /etc/php/8.3/apache2/php.ini && \
        echo "openssl.cafile=/etc/ssl/certs/cacert.pem" | sudo tee -a /etc/php/8.3/apache2/php.ini && \
        echo "curl.cainfo=/etc/ssl/certs/cacert.pem" | sudo tee -a /etc/php/8.3/apache2/php.ini && \
        
        export SSL_CERT_FILE=/etc/ssl/certs/cacert.pem && \
        export CURL_CA_BUNDLE=/etc/ssl/certs/cacert.pem 
        
        sudo ln -s /etc/ssl/certs/cacert.pem /usr/local/share/ca-certificates/cacert.pem
        sudo systemctl restart apache2



### API
    // db connection in .env
    composer install
    php bin/console doctrine:database:create --if-not-exists
    php bin/console doctrine:migrations:migrate

    php bin/console doctrine:database:create --if-not-exists && php bin/console doctrine:migrations:migrate

### Android
    Open project in android studio
    .env settings
    deploy on handy => Do NOT start the application !

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
    0. Open the API log file
    1. Android Handy registration => Fill out email and phone
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
       .env ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION=0
    5. Stop mobil application and restart it   


## Sanitize Database : Job call automatically stored DB procedure
    1. Connect from cli to the Database
    2. use database : api
    3. CREATE EVENT delete_unvalid_process ON SCHEDULE EVERY 5 SECOND ON COMPLETION PRESERVE DO   DELETE FROM auth_bridge   WHERE created_at < NOW() - INTERVAL 15 SECOND;
    4. On the Server: crontab -e
    * * * * * php /var/www/html/api/zero-intrusion-api/bin/console app:run-procedure