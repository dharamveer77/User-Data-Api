<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/upload', name: 'upload_data', methods: ['POST'])]
    public function upload(EntityManagerInterface $em, Request $request, MailerInterface $mailer): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file || $file->getClientOriginalExtension() !== 'csv') {
            return new JsonResponse(['message' => 'Invalid file type.'], Response::HTTP_BAD_REQUEST);
        }

        if (($handle = fopen($file->getPathname(), 'r')) !== false) {
            fgetcsv($handle); // Skip the header
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $user = new User();
                $user->setName($data[0]);
                $user->setEmail($data[1]);
                $user->setUsername($data[2]);
                $user->setAddress($data[3]);
                $user->setRole($data[4]);

                $em->persist($user);

                // emails asynchronously after persisting
                $email = (new Email())
                    ->from('no-reply@example.com')
                    ->to($user->getEmail())
                    ->subject('Data Uploaded')
                    ->text('Your data has been successfully uploaded.');
                $mailer->send($email);
            }
            fclose($handle);

            // Flush once to save all entities in one transaction
            $em->flush();

            return new JsonResponse(['message' => 'Data uploaded and emails sent successfully.']);
        }

        return new JsonResponse(['message' => 'Failed to process the file.'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/users', name: 'get_users', methods: ['GET'])]
    public function getUsers(EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();
        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'address' => $user->getAddress(),
                'role' => $user->getRole(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/backup', name: 'backup_database', methods: ['GET'])]
    public function backupDatabase(EntityManagerInterface $em): JsonResponse
    {
        $backupFile = 'C:\\xampp\\backup\\backup.sql';
        $backupContent = '';

        try {
            // Get all tables in the database
            $tables = $em->getConnection()->executeQuery("SHOW TABLES")->fetchAllAssociative();

            foreach ($tables as $tableRow) {
                $tableName = reset($tableRow); // Extract table name

                // Get table creation SQL
                $createTableResult = $em->getConnection()->executeQuery("SHOW CREATE TABLE `$tableName`")->fetchAssociative();
                $backupContent .= "\n\n" . $createTableResult['Create Table'] . ";\n\n";

                // Fetch all rows in the table
                $rows = $em->getConnection()->executeQuery("SELECT * FROM `$tableName`")->fetchAllAssociative();

                // Generate INSERT statements for each row
                foreach ($rows as $row) {
                    $values = array_map([$em->getConnection(), 'quote'], array_values($row));
                    $backupContent .= "INSERT INTO `$tableName` VALUES (" . implode(", ", $values) . ");\n";
                }
            }

            // Write the backup content to the file
            if (file_put_contents($backupFile, $backupContent) !== false) {
                return new JsonResponse(['message' => 'Database backup completed successfully.'], Response::HTTP_OK);
            } else {
                throw new \Exception('Failed to write backup file.');
            }

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to create database backup.', 'details' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/restore', name: 'restore_database', methods: ['POST'])]
    public function restoreDatabase(EntityManagerInterface $em): JsonResponse
    {
        $backupFile = 'C:\\xampp\\backup\\backup.sql';

        if (!file_exists($backupFile)) {
            return new JsonResponse(['error' => 'Backup file does not exist.'], Response::HTTP_NOT_FOUND);
        }

        // Read the backup file
        $backupContent = file_get_contents($backupFile);

        if ($backupContent === false) {
            return new JsonResponse(['error' => 'Failed to read backup file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            // Split the backup content into individual SQL statements
            $queries = preg_split('/;\s*[\r\n]+/', $backupContent); // Split by semicolon and new line

            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $em->getConnection()->executeStatement($query);
                }
            }

            return new JsonResponse(['message' => 'Database restored successfully.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to restore the database.', 'details' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
