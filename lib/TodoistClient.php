<?php

class TodoistClient
{
    private $token;
    private $base = "https://api.todoist.com/api/v1/";

    public function __construct($token)
    {
        $this->token = $token;
    }

    private function request($method, $endpoint, $data = null)
    {
        $url = $this->base . $endpoint;
        $ch = curl_init($url);

        $headers = [
            "Authorization: Bearer {$this->token}",
            "Content-Type: application/json"
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Log ALL calls to the WHMCS Module Log so you can verify "Close" signals
        logModuleCall(
            'todoistsync', 
            $method . ' ' . $endpoint, 
            json_encode(['url' => $url, 'payload' => $data]), 
            $response, 
            null, 
            [$this->token]
        );

        curl_close($ch);
        return json_decode($response, true);
    }

    public function createTask($data) { return $this->request("POST", "tasks", $data); }
    public function updateTask($id, $data) { return $this->request("POST", "tasks/$id", $data); }
    public function closeTask($id) { return $this->request("POST", "tasks/$id/close"); }
    public function reopenTask($id) { return $this->request("POST", "tasks/$id/reopen"); }
    public function getTask($id) { return $this->request("GET", "tasks/$id"); }
}