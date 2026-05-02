
symfony server:start
php bin/console cache:clear

 Get-Content -Path "var/log/dev.log" -Wait -Tail 10

 

update entity
    1. php bin/console make:entity
    2. php bin/console make:migration
    3. php bin/console doctrine:migrations:migrate


server 

php8.2-cli bin/console doctrine:migrations:migrate

Ez szokott a problema lenni git-tel
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/github_id_ed25519

deployment -works



Ctrl + K, Ctrl + C – kommentelés (//)
Ctrl + K, Ctrl + U – komment eltávolítása


find .git/objects/ -type f -empty | xargs rm
git fetch -p
git fsck --full



sanitize DB: // crontab php8.2-cli /kunden/homepages/12/.../htdocs/easylogin/bin/console app:run-procedure


php -r '
$key = sodium_crypto_generichash($shared-secret, "", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$message = json_encode(["userName"=>"boszormenyirobert@yahoo.com","userPassword"=>"HatarACsillagosEg"]);
$cipher = sodium_crypto_secretbox($message, $nonce, $key);
echo base64_encode($nonce . $cipher);
'


php -r '
$encrypted = base64_decode("4TfQgI8rCHy96lgiucshJzNZ78LK4qL9zv4f6eiVEQP94eMlB3yeHqetP53RBHxfXxePqJAkPCVqQXTxMaZHcNkHtTeANafFfkAUICBwfi0LhrIPdszPlrQbMYzs0XUzSvYFtmkaZsn66mJ47KxHAgxlE1dCZ");
$key = sodium_crypto_generichash($shared-secret, "", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$nonce = mb_substr($encrypted, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, "8bit");
$cipher = mb_substr($encrypted, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, "8bit");
$plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $key);
echo $plaintext;
'


Test credentials:
"idHash": "random-hash2", 
user-master-password => kacsa
"domain":"google.com"
$message = json_encode(["userName"=>"boszormenyirobert@yahoo.com","userPassword"=>"HaEnGazdagLennek"]);

FqxPdHRz2AtYhC4ustrNIdxfb0HUWdr7NtdqtuTvV11ud5FOSONJmyPV4UgNzziN+vOVvrxepR4O4kLpttmwUFtn5RgBmPLrukSLqPA0ziA6gp/x0lyjvlRAr7YSbieJeNbwU5oki5kh0S495VQC1O2vtB4=





user-master-password => EasyLogin
"idHash": "random-hash3", 
$message = json_encode(["userName"=>"boszormenyirobert@yahoo.com","userPassword"=>"HatarACsillagosEg"]);
"domain":"easypublic.com"

TfQgI8rCHy96lgiucshJzNZ78LK4qL9zv4f6eiVEQP94eMlB3yeHqetP53RBHxfXxePqJAkPCVqQXTxMaZHcNkHtTeANafFfkAUICBwfi0LhrIPdszPlrQbMYzs0XUzSvYFtmkaZsn66mJ47KxHAgxlE1dCZ