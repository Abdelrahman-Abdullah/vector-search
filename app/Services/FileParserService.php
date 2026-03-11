<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use League\Csv\Reader;
use Smalot\PdfParser\Parser;

class FileParserService
{
    public function extract(UploadedFile $file): string
    {
        $fileExtension = $this->getFileType($file);
        $filePath = $file->getPathname();

        return match ($fileExtension) {
            'txt', 'md' => $this->extractText($filePath),
            'csv' => $this->extractCsv($filePath),
            'pdf' => $this->extractPdf($filePath),
            default => throw new \InvalidArgumentException("Unsupported file type: .{$fileExtension}"),
        };
    }

    public function getFileType(UploadedFile $file): string
    {
        return  strtolower( $file->getClientOriginalExtension() );
    }

    private function extractText(string $file): string
    {
        $content = file_get_contents($file);

        if ($content === false) {
            throw new \RuntimeException('Failed to read text file content.');
        }

        return $content;
    }

    private function extractCsv(string $file): string
    {
        $csv = Reader::from($file, 'r');
        $csv->setHeaderOffset(0); // Assuming the first row contains headers

        $lines = [];
        $lines[] = implode(' | ', $csv->getHeader()); // Add headers as the first line

        foreach ($csv->getRecords() as $row) {
            $lines[] = implode(' | ', $row);
        }
        return implode("\n", $lines);

    }

    private function extractPdf(string $file): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($file);

        $pdfText = $pdf->getText();
        if (trim($pdfText) === '') {
            throw new \RuntimeException(
                'Could not extract text from PDF. ' .
                'The file may be scanned/image-based.'
            );
        }

        return $pdfText;
    }
}