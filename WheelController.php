<?php

namespace app\api\controllers;

use app\api\validators\ProvideWheelGuiValidator;
use app\api\validators\WheelValidator;
use app\common\Application;
use app\common\exceptions\DbNotFoundException;
use app\common\exceptions\DomainException;
use app\common\exceptions\TokenNotFoundException;
use app\common\exceptions\TokenValueException;
use app\common\exceptions\ValidatorException;
use app\common\exceptions\WheelMiniGameNotFoundException;
use app\common\exceptions\WheelNotFoundException;
use app\common\helpers\HeadersHelper;
use app\common\valueObjects\TokenInfo;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WheelController implements ControllerProviderInterface
{
    /**
     * @param Application|\Silex\Application $container
     * @return mixed
     */
    public function connect(\Silex\Application $container)
    {
        $authCallback = function (Request $request) use ($container) {
            
            $tokenHeaderValue = $request->headers->get('Authorization');
            
            try {
                $token = HeadersHelper::getTokenFromAuthHeaderString($tokenHeaderValue);
                $tokenInfo = $container->getTokenInfo($token);
            } catch (TokenValueException|TokenNotFoundException $e) {
                
                return $container->json(['message' => $e->getMessage()], 401);
            }
            
            $request->attributes->set('tokenInfo', $tokenInfo);
            
            return null;
        };
        
        $controller = $container->getControllerCollection();
        
        $controller->post('/wheel', function (Request $request) use ($container) {
            /** @var TokenInfo $tokenInfo */
            $tokenInfo = $request->attributes->get('tokenInfo');
            try {
                $wheelValidator = new WheelValidator($request->getContent());
                $createWheelService = $container->getServices()->getCreateWheelService();
                $wheel = $createWheelService->create($wheelValidator, $tokenInfo->getCasinoId());
            } catch (ValidatorException|DomainException $e) {
        
                return $container->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
    
            return $container->json([
                'id' => $wheel->getId(),
                'name' => $wheel->getName(),
                'sectors' => $container->getSerializers()->getSectorsSerializer()->serialize($wheel->getSectors()),
                'createdAt' => $wheel->getCreatedAt(),
                'updatedAt' => $wheel->getUpdatedAt(),
                'canEdit' => $wheel->isEditable(),
            ]);
        })
            ->before($authCallback);
        
        $controller->put('/wheel/{id}', function (int $id, Request $request) use ($container) {
            /** @var TokenInfo $tokenInfo */
            $tokenInfo = $request->attributes->get('tokenInfo');
            try {
                $validator = new WheelValidator($request->getContent());
                $wheelRepository = $container->getRepositories()->getWheelRepository();
                $wheel = $wheelRepository->getByIdAndCasinoId($id, $tokenInfo->getCasinoId());
                $sectors = $container->getServices()->getSectorsFactoryService()->create($validator->getSectorValidators(), $tokenInfo->getCasinoId());
                $wheel->update($validator->getName(), $sectors);
                $wheelRepository->update($wheel);
            } catch (ValidatorException|DomainException $e) {
        
                return $container->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            } catch (DbNotFoundException $e) {
        
                return $container->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
            }
    
            return $container->json([
                'id' => $wheel->getId(),
                'name' => $wheel->getName(),
                'sectors' => $container->getSerializers()->getSectorsSerializer()->serialize($wheel->getSectors()),
                'createdAt' => $wheel->getCreatedAt(),
                'updatedAt' => $wheel->getUpdatedAt(),
                'canEdit' => $wheel->isEditable(),
            ]);
        })
            ->assert('id', '\d+')
            ->convert('id', 'toInt')
            ->before($authCallback);
        
        $controller->delete('/wheel/{id}', function (int $id, Request $request) use ($container) {
            /** @var TokenInfo $tokenInfo */
            $tokenInfo = $request->attributes->get('tokenInfo');
            try {
                $wheelRepository = $container->getRepositories()->getWheelRepository();
                $wheel = $wheelRepository->getByIdAndCasinoId($id, $tokenInfo->getCasinoId());
                if ($wheel->isEditable()) {
                    $wheelRepository->deleteById($id);
                } else {
                    throw new DomainException('Cannot delete wheel');
                }
            } catch (DbNotFoundException|DomainException $e) {
        
                return $container->json(['message' => $e->getMessage()], 400);
            }
    
            return '';
            
        })
            ->assert('id', '\d+')
            ->convert('id', 'toInt')
            ->before($authCallback);
        
        $controller->get('/wheels-list', function (Request $request) use ($container) {
            /** @var TokenInfo $tokenInfo */
            $tokenInfo = $request->attributes->get('tokenInfo');
            
            $wheelRepository = $container->getRepositories()->getWheelRepository();
            $wheels = $wheelRepository->getAllByCasinoId($tokenInfo->getCasinoId());
    
            $result = [];
            foreach ($wheels as $wheel) {
                $result[] = [
                    'id' => $wheel->getId(),
                    'name' => $wheel->getName(),
                    'sectors' => $container->getSerializers()->getSectorsSerializer()->serialize($wheel->getSectors()),
                    'createdAt' => $wheel->getCreatedAt(),
                    'updatedAt' => $wheel->getUpdatedAt(),
                    'canEdit' => $wheel->isEditable(),
                ];
            }
            
    
            return $container->json($result);
        })
            ->before($authCallback);
        
        $controller->post('/provide-wheel-mini-game', function (Request $request) use ($container) {
            /** @var TokenInfo $tokenInfo */
            $tokenInfo = $request->attributes->get('tokenInfo');
            try {

                $valueObject = new ProvideWheelGuiValidator($request->getContent());

                $createWheelRequestService = $container->getServices()->getCreateWheelRequestService();
                $wheelMiniGame = $createWheelRequestService->create(
                    $tokenInfo->getOperatorId(),
                    $valueObject->getPlayerId(),
                    $valueObject->getWheelId(),
                    $valueObject->getProbability(),
                    $tokenInfo->getCasinoId()
                );
            } catch (ValidatorException|WheelNotFoundException|DomainException $e) {
                
                return $container->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
            
            return $container->json([
                'wheelMiniGameId' => $wheelMiniGame->getWheelMiniGameId(),
            ]);
        })
            ->before($authCallback);
        
        $controller->get('/wheel-mini-game/{id}', function (int $id, Request $request) use ($container) {
            /** @var TokenInfo $tokenInfo */
            $tokenInfo = $request->attributes->get('tokenInfo');
            try {
                $wheelMiniGameRepository = $container->getRepositories()->getWheelMiniGameRepository();
                $wheelMiniGame = $wheelMiniGameRepository->getByWheelMiniGameIdAndCasinoId($id, $tokenInfo->getCasinoId());
            } catch (WheelMiniGameNotFoundException $e) {
                
                return $container->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
            }
            
            return $container->json([
                'playerId' => $wheelMiniGame->getPlayerId(),
                'status' => $wheelMiniGame->getStatus(),
            ]);
        })
            ->assert('id', '\d+')
            ->convert('id', 'toInt')->before($authCallback);
    
        $controller->get('/games-list', function (Request $request) use ($container) {
            /** @var TokenInfo $tokenInfo */
            $tokenInfo = $request->attributes->get('tokenInfo');
            
            $casinoGameRepository = $container->getRepositories()->getCasinoGameRepository();
            $games = $casinoGameRepository->getGameList($tokenInfo->getCasinoId());
    
            $result = [];
            foreach ($games as $game) {
                $result[] = [
                    'id' => $game->getGameId(),
                    'title' => $game->getTitle(),
                ];
            }
    
            return $container->json($result);
        })->before($authCallback);
        
        return $controller;
    }
}
