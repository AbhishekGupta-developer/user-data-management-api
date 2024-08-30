<?php

// src/Controller/RestoreController.php
namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RestoreController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/api/restore', name: 'api_restore', methods: ['POST'])]
    public function restoreDatabase(): Response
    {
        $dbHost = $this->getParameter('database_host');
        $dbPort = $this->getParameter('database_port');
        $dbUser = $this->getParameter('database_user');
        $dbName = $this->getParameter('database_name');
        $backupFile = 'C:/xampp/htdocs/user-data-management-api/db_backup/backup.sql';

        // Construct the command to restore the database
        $command = [
            'mysql',
            '-h', $dbHost,
            '-P', $dbPort,
            '-u', $dbUser,
            $dbName
        ];

        // Set up the process to execute the command
        $process = new Process($command);
        $process->setTimeout(300); // Set timeout to 5 minutes (300 seconds)

        // Redirect input from the backup file
        $process->setInput(file_get_contents($backupFile));

        // Execute the command and capture output
        try {
            $process->mustRun();
            return new Response('Database restored successfully', Response::HTTP_OK);
        } catch (ProcessFailedException $exception) {
            // Log the exception details
            $this->logger->error('Restore process failed: ' . $exception->getMessage());
            return new Response(
                'Failed to restore database: ' . $exception->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
