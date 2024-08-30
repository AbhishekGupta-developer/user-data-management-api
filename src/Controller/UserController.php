<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UserController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/api/upload', name: 'api_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        $file = $request->files->get('file');

        if ($file && $file->isValid()) {
            $emails = []; // Array to store email addresses

            // Parse the CSV file
            if (($handle = fopen($file->getPathname(), 'r')) !== false) {
                // Skip the first row (header)
                fgetcsv($handle, 1000, ',');

                // Process each row in the CSV
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $user = new User();
                    $user->setName($data[0]);
                    $user->setEmail($data[1]);
                    $user->setUsername($data[2]);
                    $user->setAddress($data[3]);
                    $user->setRole($data[4]);

                    // Collect email addresses
                    $emails[] = $data[1];

                    // Persist the user entity
                    $this->entityManager->persist($user);
                }

                // Flush to save all the users
                $this->entityManager->flush();

                fclose($handle);

                // Send email notification after successful upload
                try {
                    $this->sendEmailNotification($emails);
                } catch (TransportExceptionInterface $e) {
                    // Handle the error appropriately
                    return new Response('Data uploaded successfully, but email sending failed.', Response::HTTP_OK);
                }

                return new Response('Data uploaded successfully', Response::HTTP_OK);
            } else {
                return new Response('Failed to open CSV file', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return new Response('Invalid file', Response::HTTP_BAD_REQUEST);
    }

    #[Route('/api/users', name: 'api_users', methods: ['GET'])]
    public function viewUsers(): JsonResponse
    {
        // Fetch all users from the database
        $users = $this->entityManager->getRepository(User::class)->findAll();

        // Transform entities to arrays for JSON response
        $userData = array_map(function(User $user) {
            return [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'address' => $user->getAddress(),
                'role' => $user->getRole(),
            ];
        }, $users);

        return new JsonResponse($userData);
    }

    private function sendEmailNotification(array $emails)
    {
        // Get the DSN from environment variables
        $dsn = $_ENV['MAILER_DSN'];

        // Create the transport
        $transport = Transport::fromDsn($dsn);

        // Create the Mailer
        $mailer = new Mailer($transport);

        foreach ($emails as $emailAddress) {
            // Create the email
            $email = (new Email())
                ->from($_ENV['MAILER_USERNAME'])
                ->to($emailAddress)
                ->subject('Data Upload Notification')
                ->text('The CSV data has been successfully uploaded and saved to the database.');
    
            // Send the email
            try {
                $mailer->send($email);
            } catch(TransportExceptionInterface $e) {
                // Log or handle the email send error
            }
        }
    }
}
