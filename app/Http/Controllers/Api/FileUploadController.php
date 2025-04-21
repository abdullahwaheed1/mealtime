<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    /**
     * Upload a file and return its public URL
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max size
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the file from the request
            $file = $request->file('file');
            
            // Generate a unique filename
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Store the file in the public disk
            $path = $file->storeAs('uploads', $filename, 'public');
            
            // Generate the public URL
            $url = asset('storage/' . $path);
            
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'url' => $url,
                'path' => $path
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}