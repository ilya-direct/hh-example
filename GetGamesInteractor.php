<?php

namespace app\common\integrations\casino\getGames;

use app\common\exceptions\BadResponseDataException;
use app\common\exceptions\CasinoInteractorException;
use app\common\integrations\casino\CasinoInteractorAdapter;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class GetGamesInteractor
{
    const CALL_METHOD = 'getGames';
    
    protected $casinoInteractorHelper;
    
    public function __construct(CasinoInteractorAdapter $casinoInteractorHelper)
    {
        $this->casinoInteractorHelper = $casinoInteractorHelper;
    }
    
    /**
     * @param int $casinoId
     * @return GetGamesResponse
     * @throws CasinoInteractorException
     */
    public function getGames(int $casinoId): GetGamesResponse
    {
        $casino = $this->casinoInteractorHelper->getCasino($casinoId);
       
        // ----
        $url = $casino->getEndpoint();
        $body = $this->casinoInteractorHelper->getRequestBody(self::CALL_METHOD);
        $headers = $this->casinoInteractorHelper->getRequestHeaders($body, $casino->getId());
        $request = new Request('POST', $url, $headers, $body);
        // ----
    
        $this->casinoInteractorHelper->logRequest('GetGames integration request', $request);
        try {
            $response = $this->casinoInteractorHelper->send($request);
        } catch (RequestException $e) {
            $this->casinoInteractorHelper->logErrorResponse('GetGames integration error', $e->getMessage(), $request, $e->getResponse());
            
            throw new CasinoInteractorException('No connection with casino/Bad response status');
        }
        $this->casinoInteractorHelper->logResponse('GetGames integration responded', $response);
    
        try {
            $getGamesResponse = new GetGamesResponse((string)$response->getBody());
        } catch (BadResponseDataException $e) {
            $this->casinoInteractorHelper->logErrorResponse('GetGames integration response format error', $e->getMessage(), $request, $response);
    
            throw new CasinoInteractorException('Bad casino response data');
        }
        
        return $getGamesResponse;
    }
    
}
