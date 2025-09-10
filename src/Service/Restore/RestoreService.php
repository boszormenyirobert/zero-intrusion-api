<?php

namespace App\Service\Restore;

use Psr\Log\LoggerInterface;
use App\Helper\UtilityHelper;
use App\Service\Mailer\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Restore;
use App\Repository\RestoreRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Service\Restore\Database\CrypterDatabaseRestoreService;
use App\Service\Restore\Database\RestoreDatabaseService;
use App\Exception\MissingKeyException;
use App\Exception\EntityNotFoundException;


/**
 * RestoreService handles the process of replacing a device.
 * It includes methods for sending recovery notifications, sending emails and SMS,
 * and saving identifiers to the database.
 */
final class RestoreService
{

    private $vonageClient = null;
    private $recoveryData = null;
    private $restoreHash = null;
    private $pin = null;

    public function __construct(
        private LoggerInterface $logger,
        private MailerService $mailerService,
        private UtilityHelper $utilityHelper,
        private EntityManagerInterface $entityManager,
        private ContainerBagInterface $params,
        private RestoreRepository $restoreRepository,
        private CrypterDatabaseRestoreService $crypterDatabaseRestoreService,
        private RestoreDatabaseService $restoreDatabaseService
    ) {}

    /**
     * Handle the recovery notification process.
     * This method initializes the necessary services, generates a unique device replace hash,
     * sends an email and SMS to the user, and saves the identifiers to the database.
     *
     * @param mixed $recoveryData The data required for recovery, such as email and phone number.
     * @return array An empty array indicating success.
     */
    public function recoveryNotification($recoveryData)
    {
        $this->initialization();

        $this->recoveryData = $recoveryData;

        $this->sendEmail();
        // $isSentSMS = $this->sendSMS(); // => Works

        $this->saveIdentifiers();

        return [];
    }

    /**
     * Initialize the Vonage client and generate a unique device replace hash and PIN.
     * This method is called in the constructor to set up the necessary services.
     */
    private function initialization()
    {
        $apiKey = $this->params->get('VONAGE_API_KEY');
        $apiSecret = $this->params->get('VONAGE_API_SECRET');

        $basic  = new \Vonage\Client\Credentials\Basic($apiKey, $apiSecret);
        $this->vonageClient = new \Vonage\Client($basic);

        $this->pin = $this->utilityHelper->generatePin();
        $this->restoreHash = $this->utilityHelper->getRestoreHash();
    }

    /**
     * Send an email with the uniqe url-hash to the user's email address.
     * This method uses the MailerService to send the email.
     */
    public function sendEmail()
    {
        $this->mailerService->sendEmail($this->recoveryData->getEmail("email"), $this->restoreHash);
    }

    /**
     * Send an SMS with the PIN to the user's phone number.
     * This method uses the Vonage API to send the SMS.
     * Try to catch any exceptions that may occur during the sending process.
     *
     * @return bool Returns true if the SMS was sent successfully, false otherwise.
     */
    private function sendSMS(): bool
    {
        //$phone = $this->recoveryData->getPhone();
        $phone = "004915152182871";
        $text = "Your Pin: " . $this->pin;

        try {
            $response = $this->vonageClient->sms()->send(
                new \Vonage\SMS\Message\SMS($phone, "EasyLogin", $text)
            );

            $message = $response->current();

            if ($message->getStatus() !== 0) {
                throw new \RuntimeException('SMS sending failed: ' . $message->getStatus());
            }

            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException('SMS sending error: ' . $e->getMessage());
        }
    }

    /**
     * Save the identifiers to the database.
     * This method creates a Restore object, sets its properties,
     * encrypts it, and then saves it to the database.
     */
    private function saveIdentifiers()
    {
        if (!$this->recoveryData) {
            throw new \LogicException('Recovery data is not set.');
        }

        $restore = new Restore();
        $restore->setHash($this->restoreHash);
        //$restore->setPin((int)$this->pin);

        $restore->setPin(123456);
        $restore->setPublicId($this->recoveryData->getPublicId());
        $restore->setPrivateId($this->recoveryData->getPrivateId());
        $restore->setSecret($this->recoveryData->getSecret());

        // Encrypt the Restore data before saving to database
        $encryptedRestore = $this->crypterDatabaseRestoreService->encyptSourceData($restore);
        if (!$encryptedRestore) {
            throw new \RuntimeException('Encryption failed for Restore.');
        }

        $this->restoreDatabaseService->addRestore($encryptedRestore);
    }

    /**
     * Replace the device using the validated payload.
     * This method retrieves the Restore object from the database,
     * decrypts it, and returns it as an array.
     *
     * @param array $validatedPayload
     * @return array
     */
    public function replaceValidation(array $validatedPayload): array
    {
        if (
            !isset($validatedPayload['restorePin']['data']['pin']) ||
            !isset($validatedPayload['restorePin']['replaceHash'])
        ) {
            throw new MissingKeyException('Missing required fields: pin or replaceHash');
        }

        $pin = $validatedPayload['restorePin']['data']['pin'];
        $replaceHash = $validatedPayload['restorePin']['replaceHash'];

        $restore = $this->restoreRepository->findOneBy([
            'pin' => $pin,
            'hash' => $replaceHash
        ]);

        if (!$restore) {
            throw new EntityNotFoundException('Replace device not found');
        }

        $deviceObject = $this->crypterDatabaseRestoreService->decryptFromDatabase($restore);

        return [
            'publicId' => $deviceObject->getPublicId(),
            'privateId' => $deviceObject->getPrivateId(),
            'secret' => $deviceObject->getSecret()
        ];
    }
}
