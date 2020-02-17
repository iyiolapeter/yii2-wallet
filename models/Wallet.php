<?php

namespace pso\yii2\wallet\models;

use pso\yii2\wallet\WalletModule;
use UserException;
use Yii;

/**
 * This is the model class for table "{{%wallet}}".
 *
 * @property int $id
 * @property int $user_id
 * @property int $type_id
 * @property string $balance
 * @property int $can_overdraft
 * @property string $overdraft_limit
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property $user
 * @property WalletType $type
 * @property WalletTransaction[] $walletTransactions
 */
class Wallet extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%wallet}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'type_id'], 'required'],
            [['user_id', 'can_overdraft'], 'integer'],
            [['balance', 'overdraft_limit'], 'number'],
            [['status','type_id'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['user_id', 'type_id'], 'unique', 'targetAttribute' => ['user_id', 'type_id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => WalletModule::getUserClass(), 'targetAttribute' => ['user_id' => 'id']],
            [['type_id'], 'exist', 'skipOnError' => true, 'targetClass' => WalletType::className(), 'targetAttribute' => ['type_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'type_id' => 'Type ID',
            'balance' => 'Balance',
            'can_overdraft' => 'Can Overdraft',
            'overdraft_limit' => 'Overdraft Limit',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(WalletModule::getUserClass(), ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getType()
    {
        return $this->hasOne(WalletType::className(), ['id' => 'type_id']);
    }

    public static function findByUserAndType(int $user_id, string $type, bool $create = false){
        $wallet = static::find()
            ->innerJoinWith('type')
            ->innerJoinWith('user')
            ->with(['type'])
            ->alias('wallet')
            ->where(['wallet.user_id' => $user_id, 'wallet.type_id' => $type])->one();
        if(is_null($wallet) && $create){
            $wallet = new SELF();
            $type = WalletType::findOne($type);
            if(is_null($type)){
                return null;
            }
            $wallet->setAttributes([
                'user_id' => $user_id,
                'type_id' => $type->id
            ]);
            if(!$wallet->save()){
                return null;
            }
        }
        return $wallet;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWalletTransactions()
    {
        return $this->hasMany(WalletTransaction::className(), ['wallet_id' => 'id']);
    }

    public function can($value, $checkOverdraft = true){
        $value = abs($value);
        if($checkOverdraft && $this->can_overdraft && !empty($this->overdraft_limit)){
            $overdraft = $this->overdraft_limit + $this->balance;
            if($overdraft < $value){
                throw new UserException('Overdraft limit exceeded');
            }
        }
        if($this->balance < $value){
            throw new UserException('Insufficient funds');
        }
        return true;
    }
}
