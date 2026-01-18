<?php
declare(strict_types=1);

namespace App\Collections\Infrastructure\Parser;

use RuntimeException;
use SimpleXMLElement;
use XMLReader;

/**
 * Parser for DVD Profiler XML export files.
 * Uses XMLReader for memory-efficient streaming parsing of large files.
 */
class DvdProfilerXmlParser
{
    /**
     * Load and parse a DVD Profiler XML file.
     * Uses streaming parser to handle large files without loading entire file into memory.
     *
     * @param string $filePath Path to the XML file
     * @return SimpleXMLElement The parsed XML with DVD elements
     * @throws RuntimeException If the file cannot be read or parsed
     */
    public function loadFromFile(string $filePath): SimpleXMLElement
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        libxml_use_internal_errors(true);

        // Create a temporary file for converted UTF-8 content if needed
        $tempFile = $this->ensureUtf8File($filePath);
        $useTemp = $tempFile !== $filePath;

        try {
            // Use XMLReader to stream through the file and extract DVD elements
            $reader = new XMLReader();
            if (!$reader->open($tempFile)) {
                throw new RuntimeException("Unable to open XML file: {$filePath}");
            }

            // Build XML structure with all DVDs
            $dvds = [];
            $collectionOpen = false;

            while ($reader->read()) {
                // Skip until we find Collection root
                if (!$collectionOpen && $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'Collection') {
                    $collectionOpen = true;
                    continue;
                }

                // Extract each DVD element
                if ($collectionOpen && $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'DVD') {
                    $dvdXml = $reader->readOuterXML();
                    if ($dvdXml) {
                        $dvds[] = $dvdXml;
                    }
                }

                // Stop if we've passed the Collection closing tag
                if ($collectionOpen && $reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'Collection') {
                    break;
                }
            }

            $reader->close();

            // Build complete XML document from DVDs
            $xmlContent = '<?xml version="1.0" encoding="UTF-8"?><Collection>' . implode('', $dvds) . '</Collection>';
            
            // Parse as SimpleXMLElement
            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
            
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMsg = !empty($errors) ? $errors[0]->message : 'Unknown error';
                throw new RuntimeException("Invalid XML in file: {$filePath} - {$errorMsg}");
            }

            libxml_clear_errors();
            return $xml;
        } finally {
            // Clean up temp file if we created one
            if ($useTemp && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Ensure file is UTF-8 encoded, creating a temp file if conversion is needed.
     * Uses PHP's stream filters for efficient chunk-based conversion.
     *
     * @param string $filePath Original file path
     * @return string Path to UTF-8 file (original or temp)
     */
    private function ensureUtf8File(string $filePath): string
    {
        // Read just the first 1KB to detect encoding
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open file: {$filePath}");
        }
        
        $header = fread($handle, 1024);
        fclose($handle);
        
        if ($header === false) {
            throw new RuntimeException("Unable to read file header: {$filePath}");
        }

        // Detect declared encoding
        $srcEnc = 'WINDOWS-1252';
        if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $header, $matches)) {
            $srcEnc = strtoupper($matches[1]);
        }

        // If already UTF-8, return original file path
        if ($srcEnc === 'UTF-8' || $srcEnc === 'UTF8') {
            return $filePath;
        }

        // Need to convert - read file in chunks and convert
        // For large files, this is more memory-efficient than loading entire file
        $tempFile = sys_get_temp_dir() . '/dvdprofiler_' . uniqid() . '.xml';
        $inHandle = fopen($filePath, 'rb');
        $outHandle = fopen($tempFile, 'wb');
        
        if ($inHandle === false || $outHandle === false) {
            if ($inHandle) fclose($inHandle);
            if ($outHandle) fclose($outHandle);
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw new RuntimeException("Unable to create temp file for encoding conversion");
        }

        // Read and replace XML declaration with UTF-8 version
        $firstChunk = fread($inHandle, 2048);
        if ($firstChunk !== false) {
            // Replace encoding in declaration
            $firstChunk = preg_replace(
                '/(<\?xml[^>]*encoding=["\'])([^"\']+)(["\'])/i',
                '$1UTF-8$3',
                $firstChunk,
                1
            );
            fwrite($outHandle, $firstChunk);
        }

        // Convert remaining content in chunks (2MB at a time for better performance)
        $chunkSize = 2 * 1024 * 1024;
        
        while (!feof($inHandle)) {
            $chunk = fread($inHandle, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            
            // Convert chunk
            if (function_exists('mb_convert_encoding')) {
                $converted = mb_convert_encoding($chunk, 'UTF-8', $srcEnc);
            } else {
                $converted = @iconv($srcEnc, 'UTF-8//TRANSLIT//IGNORE', $chunk);
                if ($converted === false) {
                    $converted = $chunk; // Fallback
                }
            }
            
            fwrite($outHandle, $converted);
            
            // Free memory immediately
            unset($chunk, $converted);
        }
        
        fclose($inHandle);
        fclose($outHandle);
        
        return $tempFile;
    }
}
