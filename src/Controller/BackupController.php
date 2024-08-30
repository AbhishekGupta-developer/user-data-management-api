<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BackupController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/api/backup', name: 'api_backup', methods: ['GET'])]
    public function backupDatabase(): Response
    {
        $dbHost = $this->getParameter('database_host');
        $dbPort = $this->getParameter('database_port');
        $dbUser = $this->getParameter('database_user');
        $dbName = $this->getParameter('database_name');

        // Backup file path
        $backupFile = 'C:/xampp/htdocs/user-data-management-api/db_backup/backup.sql';

        // Construct the command
        $command = [
            'mysqldump',
            '-h', $dbHost,
            '-P', $dbPort,
            '-u', $dbUser,
            $dbName
        ];

        // Set up the process to execute the command
        $process = new Process($command);
        $process->setTimeout(300); // Set timeout to 5 minutes (300 seconds)

        // Execute the command and capture output
        try {
            $process->mustRun();
            $output = $process->getOutput();

            // Write the output to the backup file
            file_put_contents($backupFile, $output);

            return new Response('Database backup created successfully', Response::HTTP_OK);
        } catch (ProcessFailedException $exception) {
            // Log the exception details
            $this->logger->error('Backup process failed: ' . $exception->getMessage());
            return new Response(
                'Failed to create database backup: ' . $exception->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
