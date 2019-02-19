<?php

namespace app\api\validators;

use app\api\valueObjects\BestSpinsValidatedObject;
use app\common\exceptions\ValidatorException;

class BestSpinsPrizeValidator
{
    const SPINS_QUANTITY = 'spinsQuantity';
    const ACTIVATE_TO_PERIOD = 'activateToPeriod';
    const ACTIVATED_PERIOD = 'activatedPeriod';
    const BET_LEVEL = 'betLevel';
    const GAME_ID = 'gameId';

    const MAX_PERIOD = 2147483647; // 2 ^ 31 - 1 half of int
    const MAX_SPINS_QUANTITY = 1000000; // a million

    protected $betLevels = ['min', 'mid', 'max'];
    protected $spinsQuantity;
    protected $activateToPeriod;
    protected $activatedPeriod;
    protected $betLevel;
    protected $gameId;

    /**
     * @param array $config
     * @return BestSpinsValidatedObject
     * @throws ValidatorException
     */
    public function validate(array $config)
    {
        if (!isset($config[self::SPINS_QUANTITY]) || !is_int($config[self::SPINS_QUANTITY])) {

            throw new ValidatorException('Best spins must have `spinsQuantity` parameter (int)');
        }
        $this->spinsQuantity = $config[self::SPINS_QUANTITY];

        if (!isset($config[self::ACTIVATE_TO_PERIOD]) || !is_int($config[self::ACTIVATE_TO_PERIOD])) {

            throw new ValidatorException('Best spins must have `activateToPeriod` parameter (timestamp)');
        }
        $this->activateToPeriod = $config[self::ACTIVATE_TO_PERIOD];

        if (!isset($config[self::ACTIVATED_PERIOD]) || !is_int($config[self::ACTIVATED_PERIOD])) {

            throw new ValidatorException('Best spins must have `activatedPeriod` parameter (timestamp)');
        }
        $this->activatedPeriod = $config[self::ACTIVATED_PERIOD];

        if (!isset($config[self::BET_LEVEL]) || !is_string($config[self::BET_LEVEL])) {

            throw new ValidatorException('Best spins must have `betLevel` parameter (string)');
        }
        $this->betLevel = $config[self::BET_LEVEL];

        if (!isset($config[self::GAME_ID]) || !is_string($config[self::GAME_ID])) {

            throw new ValidatorException('Best spins must have `gameId` parameter (string)');
        }
        $this->gameId = $config[self::GAME_ID];

        $this->additionalValidation();

        $object = new BestSpinsValidatedObject(
            $this->spinsQuantity,
            $this->activateToPeriod,
            $this->activatedPeriod,
            $this->betLevel,
            $this->gameId
        );

        return $object;
    }

    /**
     * Additional Validation in order to send only correct data to casino
     * (it can be omitted(because this validation exists in domain),
     * but then it will produce trash traffic between wheel and casino)
     *
     * @throws ValidatorException
     */
    protected function additionalValidation()
    {
        if ($this->spinsQuantity < 1 || $this->spinsQuantity > self::MAX_SPINS_QUANTITY) {

            throw new ValidatorException('Spins quantity must be > 0 and <= ' . self::MAX_SPINS_QUANTITY);
        }

        if ($this->activateToPeriod < 1 || $this->activateToPeriod > self::MAX_PERIOD) {

            throw new ValidatorException('ActivateToPeriod must be > 0 and <= ' . self::MAX_PERIOD);
        }

        if ($this->activatedPeriod < 1 || $this->activatedPeriod > self::MAX_PERIOD) {

            throw new ValidatorException('ActivatedPeriod must be > 0 and <= ' . self::MAX_PERIOD);
        }

        if (!in_array($this->betLevel, $this->betLevels)) {

            throw new ValidatorException('Bet level must be in ' . implode('|', $this->betLevels));
        }
    }

}
