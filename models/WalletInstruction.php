<?php

namespace pso\yii2\wallet\models;

use pso\yii2\base\libs\UUID;
use Yii;
use yii\base\Model;
use pso\yii2\wallet\WalletModule;
use pso\yii2\wallet\models\WalletTransaction;
use yii\base\InvalidCallException;

/**
 * This is the model class for manipulating user wallets.
 *
 * @property string $reference
 * @property int $wallet_id
 * @property string $type
 * @property string $value
 * @property string $tag
 * @property string $narration
 * @property string $status
 *
 * @property Wallet $wallet
 */
class WalletInstruction extends Model
{
    const TYPE_CREDIT = WalletTransaction::TYPE_CREDIT;
    const TYPE_DEBIT = WalletTransaction::TYPE_DEBIT;

    public $reference;
    public $wallet_id;
    public $user_id;
    public $wallet_type;
    public $value;
    public $tag;
    public $narration;
    public $status;

    private $type;
    private $wallet;

    public function __construct($type, $config = [])
    {
        $this->type = $type;
        parent::__construct($config);
    }

    public function rules(){
        return [
            ['value','required'],
            [['wallet_id'], 'required', 'when' => function($model){
                return !isset($model->user_id) || !isset($model->wallet_type);
            }],
            [['user_id','wallet_type'], 'required', 'when' => function($model){
                return !isset($model->wallet_id);
            }],
            [['wallet_type','narration','status','reference','tag'], 'string'],
            ['status', 'default', 'value' => 'completed'],
            ['tag', 'default', 'value' => function(){
                return $this->type === SELF::TYPE_CREDIT?'credit':'debit';
            }],
            ['narration', 'default', 'value' => function(){
                return $this->type === SELF::TYPE_CREDIT?'Credit received':'Debit';
            }],
            ['reference', 'default', 'value' => function(){
                return UUID::v4();
            }],
            ['status', 'in', 'range' => ['completed']],
            [['wallet_id'], 'exist', 'skipOnError' => true, 'targetClass' => Wallet::className(), 'targetAttribute' => ['wallet_id' => 'id'], 'when' => function(){
                return empty($this->wallet);
            }],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => WalletModule::getUserClass(), 'targetAttribute' => ['user_id' => 'id']],
            [['wallet_type'], 'exist', 'skipOnError' => true, 'targetClass' => WalletType::className(), 'targetAttribute' => ['wallet_type' => 'id']],
        ];
    }

    public function setWallet(Wallet $wallet){
        $this->wallet = $wallet;
        $this->wallet_id = $wallet->id;
    }

    public function getType(){
        return $this->type;
    }

    protected function loadWallet(){
        if(!empty($this->wallet)){
            return true;
        }
        $wallet = isset($this->wallet_id)?Wallet::findOne($this->wallet_id):Wallet::findByUserAndType($this->user_id, $this->wallet_type, $this->type === SELF::TYPE_CREDIT);
        if(is_null($wallet)){
            throw new InvalidCallException('Wallet not found!');
        }
        $this->setWallet($wallet);
        return true;
    }

    public function transformValue($value){
        if($this->type === SELF::TYPE_CREDIT){
            return abs($value);
        } else if($this->type === SELF::TYPE_DEBIT){
            return abs($value) * -1;
        }
        return $value;
    }

    private function validateTransaction(){
        if($this->type === SELF::TYPE_DEBIT){
            return $this->wallet->can($this->value, $this->type);
        }
        return true;
    }

    protected function createTransactionRecord(){
        $record = Yii::createObject(WalletTransaction::className());
        $record->setAttributes($this->getAttributes() + [
            'type' => $this->type,
            'status' => $this->status
        ]);
        return $record;
    }

    public function send(){
        if(!$this->validate()){
            return false;
        }
        $this->loadWallet();
        $isolated = false;
        $db = Wallet::getDb();
        $transaction  = $db->getTransaction();
        if(is_null($transaction)){
            $isolated = true;
            $transaction = $db->beginTransaction(\yii\db\Transaction::SERIALIZABLE);
        }
        try {
            $this->validateTransaction();
            $this->wallet->balance += $this->transformValue($this->value);
            $record = $this->createTransactionRecord();
            if($this->wallet->save() && $record->save()){
                if($isolated){
                    $transaction->commit();
                }
                return true;
            }
            if($isolated){
                $transaction->rollBack();
            }
            return false;
        } catch (\Throwable $th) {
            if($isolated){
                $transaction->rollBack();
            }
            throw $th;
        }
    }

    public static function transfer(array $from, array $to, $value, string $reference){
        $debit = new static(SELF::TYPE_DEBIT);
        $debit->setAttributes($from);
        $credit = new static(SELF::TYPE_CREDIT);
        $credit->setAttributes($to);
        $debit->value = $credit->value = $value;
        $debit->reference = $credit->reference = $reference;
        $isolated = false;
        $db = Wallet::getDb();
        $transaction  = $db->getTransaction();
        if(is_null($transaction)){
            $isolated = true;
            $transaction = $db->beginTransaction(\yii\db\Transaction::SERIALIZABLE);
        }
        try {
            $sent = $debit->send();
        } catch (\Throwable $th) {
            if($isolated){
                $transaction->rollBack();
            }
            throw $th;
        }
    }

}