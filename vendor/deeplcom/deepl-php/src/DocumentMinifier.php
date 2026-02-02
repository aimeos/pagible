<?php

// Copyright 2024 DeepL SE (https://www.deepl.com)
// Use of this source code is governed by an MIT
// license that can be found in the LICENSE file.

namespace DeepL;

/**
 * Class that implements document minification: Stripping supported files like pptx and docx
 * of their media (images, videos, etc) before uploading them to the DeepL API to be translated.
 * This allows users to translate files that would usually hit the size limit for files.
 *
 * Please note the following:
 * 1. To use this class, you first need to check by calling `DocumentMinifier::canMinifyFile` if
 * the file type is supported. This class performs no further checks.
 * 2. The `DocumentMinifier` is stateful, so you cannot use it to minify multiple documents at once.
 * You need to create a new `DocumentMinifier` object per document.
 * 3. Be very careful when providing a custom `tempDir` when instantiating the class. For example,
 * `deminifyDocument` will delete the entire `tempDir` with `cleanup` set to `true` (disabled
 * by default). In order not to lose any data, ideally always call `new DocumentMinifier()` in order
 * to get a fresh temporary directory.
 * 4. If an error occurs during minification, either a `DocumentMinificationException` or
 * a `DocumentDeminificationException` will be thrown, depending on which phase the error
 * occured in.
 *
 * The document minification process works in 2 phases:
 * 1. Minification: The document is extracted into a temporary directory, the media files are backed up,
 * the media in the document is replaced with placeholders and a minified document is created.
 * 2. Deminification: The minified document is extracted into a temporary directory, the media backups are
 * reinserted into the extracted document, and the document is deminified into the output path.
 * If `cleanup` is enabled, the minification phase will delete the folder with the extracted document and
 * the deminification phase will delete the entire temporary directory.
 * Note that by default, the input file will be kept on disk, and as such no further backups of media etc.
 * are made (as they are all available from the input file).
 *
 * Example usage:
 *
 * $inputFile = '/home/exampleUser/document.pptx';
 * $outputFile = '/home/exampleUser/document_ES.pptx';
 * $minifier = new DocumentMinifier();
 * if ($minifier->canMinifyFile($inputFile)) {
 *   try {
 *     $minifier->minifyDocument($inputFile, true);
 *     $minifiedFile = $minifier->getMinifiedDocFile($inputFile);
 *     // process file $minifiedFile, e.g. translate it with DeepL
 *     $minifier->deminifyDocument($inputFile, $outputFile, true);
 *     // process file $outputFile
 *   } catch (DocumentMinificationException $e) {
 *     // handle exception during minification, e.g. print list of media, clean up temporary directory, etc
 *   } catch (DocumentDeminificationException $e) {
 *     // handle exception during deminification, e.g. save minified document, clean up temporary directory, etc
 *   } catch (DocumentTranslationException $e) {
 *     // handle general DocTrans exception (mostly useful if document is translated between minnification
 *     // and deminification)
 *   }
 * }
 */
class DocumentMinifier
{
    /**
     * Which input document types are supported for minification.
     */
    const SUPPORTED_DOCUMENT_TYPES = ['pptx' => true, 'docx' => true];
    /**
     * Which media formats in the documents are supported for minification.
     */
    const SUPPORTED_MEDIA_FORMATS = [
        // Image formats
        'png' => true,
        'jpg' => true,
        'jpeg' => true,
        'emf' => true,
        'bmp' => true,
        'tiff' => true,
        'wdp' => true,
        'svg' => true,
        'gif' => true,
        // Video formats
        // Taken from https://support.microsoft.com/en-gb/office/video-and-audio-file-formats-supported-in-powerpoint-d8b12450-26db-4c7b-a5c1-593d3418fb59
        'mp4' => true,
        'asf' => true,
        'avi' => true,
        'm4v' => true,
        'mpg' => true,
        'mpeg' => true,
        'wmv' => true,
        'mov' => true,
        // Audio formats, taken from the same URL as video
        'aiff' => true,
        'au' => true,
        'mid' => true,
        'midi' => true,
        'mp3' => true,
        'm4a' => true,
        'wav' => true,
        'wma' => true
    ];
    const EXTRACTED_DOC_DIR_NAME = 'extracted_doc';
    const ORIGINAL_MEDIA_DIR_NAME = 'original_media';
    const MINIFIED_DOC_FILE_BASE_NAME = 'minifiedDoc';
    const MINIFIED_DOC_SIZE_LIMIT_WARNING = 5000000;

    private $tempDir;

    public function __construct(?string $tempDir = null)
    {
        $this->tempDir = $tempDir;
        if ($this->tempDir === null) {
            $this->tempDir = DocumentMinifier::createTemporaryDirectory();
        }
    }

    public static function canMinifyFile(string $inputFile): bool
    {
        return array_key_exists(
            DocumentMinifier::getFileExtension($inputFile),
            DocumentMinifier::SUPPORTED_DOCUMENT_TYPES
        );
    }

    public function getMinifiedDocFile(string $inputFileName): string
    {
        return $this->tempDir . '/' . DocumentMinifier::MINIFIED_DOC_FILE_BASE_NAME . '.' .
            DocumentMinifier::getFileExtension($inputFileName);
    }

    public function getExtractedDocDirectory(): string
    {
        return $this->tempDir . '/' . DocumentMinifier::EXTRACTED_DOC_DIR_NAME;
    }

    public function getOriginalMediaDirectory(): string
    {
        return $this->tempDir . '/' . DocumentMinifier::ORIGINAL_MEDIA_DIR_NAME;
    }

    /**
     * Minifies a given document using the given `tempDir`, by extracting it as a ZIP file and
     * replacing all supported media files with a small placeholder.
     * Created file will be inside the `tempDir`, the filename can be retrieved by calling
     * `DocumentMinifier::getMinifiedDocFile($tempDir)`.
     * Note that this method will minify the file without any checks, you should first call
     * `DocumentMinifier::canMinifyFile` on the input file.
     * If `cleanup` is set to `true`, the extracted document will be deleted afterwards, and only
     * the original media and the minified file will remain in the `tempDir`.
     * @param string inputFilePath file to be minified
     * @param bool cleanup if `true`, will delete the extracted document files from the temporary directory.
     *      Otherwise, the files will remain (useful for debugging).
     * @return string the path of the minified document. Can also be retrieved by calling `getMinifiedDocFile`
     */
    public function minifyDocument(string $inputFilePath, $cleanup = false): string
    {
        $extractedDocDirectory = $this->getExtractedDocDirectory();
        $mediaDir = $this->getOriginalMediaDirectory();
        $minifiedDocFilePath = $this->getMinifiedDocFile($inputFilePath);

        $this->extractZipTo($inputFilePath, $extractedDocDirectory, DocumentMinificationException::class);
        $this->exportMediaToMediaDirAndReplace($extractedDocDirectory, $mediaDir);
        $this->createZippedDocumentFromUnzippedDirectory(
            $extractedDocDirectory,
            $minifiedDocFilePath,
            DocumentMinificationException::class
        );
        if ($cleanup) {
            $this->recursivelyDeleteDirectory($extractedDocDirectory, DocumentMinificationException::class);
        }
        $filesizeResponse = filesize($minifiedDocFilePath);
        if ($filesizeResponse !== false && $filesizeResponse) {
            if ($filesizeResponse > DocumentMinifier::MINIFIED_DOC_SIZE_LIMIT_WARNING) {
                trigger_error(
                    'The input file could not be minified below 5 MB, likely a media type is unsupported. This might '
                    .'cause translation to fail.',
                    E_USER_WARNING
                );
            }
        }
        return $minifiedDocFilePath;
    }

    /**
     * Deminifies a given file at `inputFilePath` by reinserting its original media in `tempDir` and stores
     * the resulting document in `outputFilePath`. If `cleanup` is set to `true`, it will delete the
     * `tempDir` afterwards, otherwise nothing will happen after the deminification.
     *
     * @param string inputFilePath Document to be deminified with its media.
     * @param string outputFilePath Where the final (deminified) document will be stored.
     * @param bool cleanup Determines if the `tempDir` is deleted at the end of this method.
     */
    public function deminifyDocument(
        string $inputFilePath,
        string $outputFilePath,
        bool $cleanup = false
    ): void {
        $extractedDocDirectory = $this->getExtractedDocDirectory();
        $mediaDir = $this->getOriginalMediaDirectory();
        if (!mkdir($extractedDocDirectory)) {
            throw new DocumentDeminificationException(
                "Exception when deminifying, could not create directory at $extractedDocDirectory."
            );
        }

        $this->extractZipTo($inputFilePath, $extractedDocDirectory, DocumentDeminificationException::class);
        $this->replaceImagesInDir($extractedDocDirectory, $mediaDir);
        $this->createZippedDocumentFromUnzippedDirectory(
            $extractedDocDirectory,
            $outputFilePath,
            DocumentDeminificationException::class
        );
        if ($cleanup) {
            $this->recursivelyDeleteDirectory($this->tempDir, DocumentDeminificationException::class);
        }
    }

    /**
     * Creates a temporary directory for use in the `DocumentMinifier` class. Uses the system's temporary directory.
     * @return string The path of the created temporary directory
     */
    public static function createTemporaryDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . '/document_minification_' . uniqid();
        while (file_exists($tempDir)) {
            usleep(1);
            $tempDir = sys_get_temp_dir() . '/document_minification_' . uniqid();
        }
        if (!mkdir($tempDir)) {
            throw new DocumentMinificationException("Failed creating temporary directory at $tempDir.");
        }
        return $tempDir;
    }

    /**
     * Deletes all temporary files that the `DocumentMinifier` created. Can be used when an exception occurs
     * during (De)Minification to ensure no data is left over.
     */
    public function cleanupCreatedFiles(): void
    {
        $this->recursivelyDeleteDirectory($this->tempDir);
    }

    private function extractZipTo(string $zippedDocumentPath, string $extractionDir, string $exceptionClass)
    {
        if (!is_dir($extractionDir)) {
            if (!mkdir($extractionDir, 0777, true)) {
                throw new $exceptionClass(
                    "Exception when extracting document: Failed to create directory $extractionDir"
                );
            }
        }
        $zip = new \ZipArchive();
        $openResult = $zip->open($zippedDocumentPath);
        if ($openResult !== true) {
            throw new $exceptionClass(
                "Exception when extracting document: Failed to open $zippedDocumentPath as a ZIP file."
            );
        }
        if (!$zip->extractTo($extractionDir)) {
            throw new $exceptionClass(
                "Exception when extracting document: Failed to extract $zippedDocumentPath to $extractionDir."
            );
        }
        $zip->close();
    }

    private function exportMediaToMediaDirAndReplace(string $inputDirectory, string $mediaDirectory)
    {
        $imageData = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($inputDirectory));
        foreach ($iterator as $file) {
            if ($file->isFile() && array_key_exists($file->getExtension(), DocumentMinifier::SUPPORTED_MEDIA_FORMATS)) {
                $curFilePath = $file->getPathname();
                $relativePath = substr($curFilePath, strlen($inputDirectory) + 1);
                $mediaPath = $mediaDirectory . '/' . $relativePath;
                $mediaDir = dirname($mediaPath);
                if (!file_exists($mediaDir)) {
                    if (!mkdir($mediaDir, 0777, true)) {
                        throw new DocumentMinificationException(
                            "Exception when extracting document: Failed to create directory at $mediaDir."
                        );
                    }
                }
                if (!rename($curFilePath, $mediaPath)) {
                    throw new DocumentMinificationException(
                        "Exception when backing up document media: Failed to move $curFilePath to $mediaPath."
                    );
                }
                if (!$this->storePlaceholderAt($curFilePath)) {
                    throw new DocumentMinificationException(
                        "Exception when minifying document: Failed to store replacement data at $curFilePath."
                    );
                }
            }
        }
        return $imageData;
    }

    private function storePlaceholderAt(string $filename): bool
    {
        $putContentsResp = file_put_contents($filename, 'DeepL Media Placeholder');
        if ($putContentsResp === false) {
            return false;
        }
        return true;
    }

    private function replaceImagesInDir(string $directory, string $mediaDirectory)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($mediaDirectory));
        foreach ($iterator as $file) {
            if ($file->isFile() && array_key_exists($file->getExtension(), DocumentMinifier::SUPPORTED_MEDIA_FORMATS)) {
                $relativePath = substr($file->getPathname(), strlen($mediaDirectory) + 1);
                $curMediumPath = $directory . '/' . $relativePath;
                $curMediumDir = dirname($curMediumPath);
                if (!file_exists($curMediumDir)) {
                    if (!mkdir($curMediumDir, 0777, true)) {
                        throw new DocumentDeminificationException(
                            "Exception when reinserting images. Failed to create directory at $curMediumDir."
                        );
                    }
                }
                if (!rename($file->getPathname(), $curMediumPath)) {
                    throw new DocumentDeminificationException(
                        "Exception when reinserting images. Failed to move media back to $curMediumPath."
                    );
                }
            }
        }
    }

    private function createZippedDocumentFromUnzippedDirectory(
        string $directory,
        string $outputFilePath,
        string $exceptionClass
    ) {
        $zip = new \ZipArchive();
        $openResult = $zip->open($outputFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new $exceptionClass("Failed creating a zip file at $outputFilePath");
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($files as $_ => $file) {
            if ($file->isFile()) {
                if (!$zip->addFile($file, str_replace($directory . '/', '', $file))) {
                    $filePathname = $file->getPathname();
                    throw new $exceptionClass("Failed adding file at $filePathname to zip at $outputFilePath.");
                }
            }
        }
        if (!$zip->close()) {
            throw new $exceptionClass("Failed closing ZIP file at $outputFilePath.");
        }
    }

    private static function getFileExtension(string $filePath)
    {
        return pathinfo($filePath, PATHINFO_EXTENSION);
    }

    public static function recursivelyDeleteDirectory(
        string $dir,
        $exceptionToThrow = DocumentMinificationException::class
    ) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            if ($objects === false) {
                throw new $exceptionToThrow(
                    "Failed scanning directory $dir when recursively deleting that directory."
                );
            }
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (filetype($dir . '/' . $object) === 'dir') {
                        DocumentMinifier::recursivelyDeleteDirectory($dir . '/' . $object, $exceptionToThrow);
                    } else {
                        $objectToDelete = $dir . '/' . $object;
                        if (!unlink($objectToDelete)) {
                            throw new $exceptionToThrow(
                                "Failed deleting $objectToDelete when recursively deleting directory $dir"
                            );
                        }
                    }
                }
            }
            reset($objects);
            if (!rmdir($dir)) {
                throw new $exceptionToThrow(
                    "Failed deleting empty directory $dir when recursively deleting that directory"
                );
            }
        }
    }
}
