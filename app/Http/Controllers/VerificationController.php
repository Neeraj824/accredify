<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VerificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class VerificationController extends Controller
{
    public function verify(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();
        // dd($user);
        $validator = Validator::make($request->all(), [
            'json_file' => 'required|file|max:2048', // 2MB maximum file size
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid JSON file.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (empty($user)) {
            return response()->json(['error' => 'Invalid User.'], Response::HTTP_UNAUTHORIZED);
        }

        // Get the JSON file content from the request

        $jsonFile = $request->file('json_file');
        $content = file_get_contents($jsonFile->getRealPath());
        
        
        // Parse the JSON content
        $jsonData = json_decode($content, true);
        
        // Verify the JSON file and get the result
        $verificationResult = $this->performVerification($jsonData);
        // Store the verification result in the database
        $this->storeVerificationResult($user->id, 'json', $verificationResult);
        // $this->storeVerificationResult(1, 'json', $verificationResult);

        // Return the response
        return response()->json(['data' => [
            'issuer' => $jsonData['data']['issuer']['name'],
            'result' => $verificationResult
        ]]);
    }

    private function performVerification(array $jsonData)
    {
        // Verify Condition 1: Check for valid recipient
        if (empty($jsonData['data']['recipient']['name']) || empty($jsonData['data']['recipient']['email'])) {
            return 'invalid_recipient';
        }
        // Verify Condition 2: Check for valid issuer and DNS-DID
        $issuer = $jsonData['data']['issuer'];
        // dd($issuer);
        if (empty($issuer['name']) || empty($issuer['identityProof']) ||
            empty($issuer['identityProof']['type']) || $issuer['identityProof']['type'] !== 'DNS-DID') {
            return 'invalid_issuer';
        }

        $dnsLookupUrl = 'https://dns.google/resolve?name=' . urlencode($issuer['identityProof']['location']) . '&type=TXT';
        $dnsResponse = Http::get($dnsLookupUrl)->json();
        $dnsRecords = Arr::get($dnsResponse, 'Answer', []);

        // Check if the DNS TXT record contains the key
        $keyToFind = $issuer['identityProof']['key'];
        $dnsRecordFound = false;
        foreach ($dnsRecords as $record) {
            if (strpos($record['data'], $keyToFind) !== false) {
                $dnsRecordFound = true;
                break;
            }
        }

        if (!$dnsRecordFound) {
            return 'invalid_issuer';
        }

        // Verify Condition 3: Check for valid signature
        $targetHash = $this->computeTargetHash($jsonData['data']);
        if ($targetHash !== $jsonData['signature']['targetHash']) {
            return 'invalid_signature';
        }

        // All conditions are met; the file is verified
        return 'verified';
    }

    private function computeTargetHash(array $data)
    {
        
        // Flatten the JSON data and compute individual hashes
        $newJsonFile = [];
        foreach($data as $key=>$value){

            if($key == 'recipient'){
                array_push($newJsonFile, hash('sha256', json_encode([$key.'.name'=>$value['name']])));
                array_push($newJsonFile, hash('sha256', json_encode([$key.'.email'=>$value['email']])));
            }
            elseif($key == 'issuer'){
                array_push($newJsonFile, hash('sha256', json_encode([$key.'.name'=>$value['name']])));
                if($value['identityProof']){
                    array_push($newJsonFile, hash('sha256', json_encode([$key.'.identityProof.type'=>$value['identityProof']['type']])));
                    array_push($newJsonFile, hash('sha256', json_encode([$key.'.identityProof.key'=>$value['identityProof']['key']])));
                    array_push($newJsonFile, hash('sha256', json_encode([$key.'.identityProof.location'=>$value['identityProof']['location']])));
                }  
            }
            else{
                array_push($newJsonFile, hash('sha256', json_encode([$key=>$value])));
            }
        } 
        sort($newJsonFile);
        $targetHash = hash('sha256', implode('', $newJsonFile));
        return $targetHash;
    }

    private function storeVerificationResult($userId, $fileType, $verificationResult)
    {
        // Store the verification result in the database
        VerificationResult::create([
            'user_id' => $userId,
            'file_type' => $fileType,
            'verification_result' => $verificationResult,
        ]);
    }
}
