<?php

namespace pso\yii2\wallet;

use Yii;
use pso\yii2\base\Module;

/**
 * user module definition class
 */
class WalletModule extends Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'pso\yii2\wallet\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
    }

    public static function getPsoId(){
        return 'wallet';
    }

    public static function getUserClass(){
        return static::coalescePsoParams(['wallet.user.class','user.class']);
    }
}
