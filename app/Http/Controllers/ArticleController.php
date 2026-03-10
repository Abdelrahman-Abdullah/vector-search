<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Services\FileParserService;

class ArticleController extends Controller
{
    public function __construct(
        public FileParserService $fileParserService
    ){}

   public function upload(UploadFileRequest $request)
   {
        $file = $request->file('file');

        try {
            $content = $this->fileParserService->extract($file);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to parse file: ' . $e->getMessage()], 500);
        }

        if (trim($content) === '') {
            return response()->json(['error' => 'No text could be extracted from the file.'], 400);
        }


   }
}
