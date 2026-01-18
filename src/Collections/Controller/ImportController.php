<?php
declare(strict_types=1);

namespace App\Collections\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Collections\Service\ImportService;

/**
 * Import controller for DVD Profiler XML file uploads.
 * POST /collections/import
 */
class ImportController
{
    public function __construct(private ImportService $importService)
    {
    }

    /**
     * Import DVD Profiler XML from uploaded file.
     * Accepts multipart/form-data with 'file' field.
     */
    public function import(Request $request, Response $response): Response
    {
        try {
            // Get uploaded files
            $uploadedFiles = $request->getUploadedFiles();

            if (empty($uploadedFiles) || !isset($uploadedFiles['file'])) {
                return $this->errorResponse(
                    $response,
                    "Missing file upload. Please provide a 'file' field in multipart/form-data.",
                    400
                );
            }

            $file = $uploadedFiles['file'];

            // Validate file type
            $filename = $file->getClientFilename();
            if (!$filename || !preg_match('/\.xml$/i', $filename)) {
                return $this->errorResponse(
                    $response,
                    "Invalid file type. Expected XML file.",
                    400
                );
            }

            // Read file content
            $file->moveTo($tempPath = sys_get_temp_dir() . '/' . uniqid('import_', true) . '.xml');
            
            try {
                $result = $this->importService->importFromXmlFile($tempPath);
                
                // Clean up temp file
                @unlink($tempPath);

                $statusCode = $result->success ? 200 : 207; // 207 Multi-Status if partial success
                
                $response->getBody()->write(json_encode($result->toArray()));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus($statusCode);
            } catch (\Exception $e) {
                // Clean up temp file on error
                @unlink($tempPath);
                throw $e;
            }
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse($response, "Internal server error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Helper method to return error response.
     */
    private function errorResponse(Response $response, string $message, int $statusCode): Response
    {
        $error = [
            'status' => 'error',
            'message' => $message,
        ];

        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
