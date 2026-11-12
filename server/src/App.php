<?php

class App {

    public function healthCheck() : \Api\HealthResponse {
        $response  = new \Api\HealthResponse();
        return $response;
    }
    
}