<?php

namespace pso\yii2\wallet\models;

use Yii;
use yii\base\UserException;
use pso\yii2\base\libs\UUID;
use yii\base\ErrorException;
use pso\yii2\wallet\WalletModule;
use yii\base\InvalidCallException;
use yii\web\NotFoundHttpException;
use pso\yii2\base\behaviors\DateTimeBehavior;
use pso\yii2\wallet\models\WalletInstruction;

/**
 * This is the model class for table "{{%wallet_transaction}}".
 *
 * @property int $id
 * @property string $reference
 * @property int $wallet_id
 * @property string $type
 * @property string $value
 * @property string $tag
 * @property string $narration
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Wallet $wallet
 */
class WalletTransaction extends \yii\db\ActiveRecord
{
    const TYPE_CREDIT = "cr";
    const TYPE_DEBIT ="dr";

    const TAG_CREDIT = "credit";
    const TAG_DEBIT = "debit";
    const TAG_WIN = "win";
    const TAG_PLAY = "play";
    const TAG_SYSTEM = "system";
    const TAG_REVERSAL = "reversal";

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%wallet_transaction}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'value', 'wallet_id'], 'required'],
            [['type', 'tag', 'status'], 'string'],
            ['type', 'in', 'range' => [self::TYPE_CREDIT, self::TYPE_DEBIT]],
            [['value'], 'number'],
            [['value'], 'filter', 'filter'=>[$this, 'transformValue']],
            [['wallet_id'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['reference', 'narration'], 'string', 'max' => 255],
            [['reference', 'wallet_id', 'type'], 'unique', 'targetAttribute' => ['reference', 'wallet_id', 'type']],
            [['wallet_id'], 'exist', 'skipOnError' => true, 'targetClass' => Wallet::className(), 'targetAttribute' => ['wallet_id' => 'id']]
        ];
    }

    public function behaviors()
    {
        return [
            DateTimeBehavior::className()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'reference' => 'Reference',
            'wallet_id' => 'Wallet ID',
            'type' => 'Type',
            'value' => 'Value',
            'tag' => 'Tag',
            'narration' => 'Narration',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWallet()
    {
        return $this->hasOne(Wallet::className(), ['id' => 'wallet_id']);
    }

    public function transformValue($value){
        if($this->type === SELF::TYPE_CREDIT){
            return abs($value);
        } else if($this->type === SELF::TYPE_DEBIT){
            return abs($value) * -1;
        }
        return $value;
    }

    public function beforeSave($insert)
    {
        if(!parent::beforeSave($insert)){
            return false;
        }
        if($this->isNewRecord && empty($this->reference)){
            $this->reference = UUID::v4();
        }
        return true;
    }

    public static function findExisting($type, $reference, $wallet){
        return static::findOne(['type' => $type, 'reference' => $reference, 'wallet_id' => $wallet]);
    }

    public static function transfer($from, $to, $value, $reference, $narration = NULL, $tag = 'transfer')
    {
        $isolated = false;
        $db = static::getDb();
        $transaction  = $db->getTransaction();
        if(is_null($transaction)){
            $isolated = true;
            $transaction = $db->beginTransaction(\yii\db\Transaction::SERIALIZABLE);
        }
        try {
            $credit_narration = $debit_narration = $narration;
            if(is_array($narration)){
                $debit_narration = $narration['debit'];
                $credit_narration = $narration['credit'];
            }
            $credit_tag = $debit_tag = $tag;
            if(is_array($tag)){
                $debit_tag = $tag['debit']??'debit';
                $credit_tag = $tag['credit']??'credit';
            }
            $debit = new static();
            $debit->setAttributes([
                'type' => static::TYPE_DEBIT,
                'wallet_id' => $from,
                'value' => $value,
                'reference' => $reference,
                'narration' => $debit_narration?:'Wallet to Wallet Transfer',
                'tag' => $debit_tag
            ]);
            if(!$debit->save(false)){
                throw new UserException('Wallet could not be debited');
            }
            $credit = static::credit($to, $value, $reference, $credit_narration?:'Wallet to Wallet Transfer');
            if($isolated){
                $transaction->commit();
            }
            return [
                'debit' => $debit,
                'credit' => $credit
            ];
        } catch (\Throwable $th) {
            if($isolated){
                $transaction->rollBack();
            }
            throw $th;
        }
    }

    public static function credit($wallet, $value, $reference, $narration, $throwDup = true){
        $credit_wallet = Wallet::findByUserAndType($wallet[0], $wallet[1], true);
        if(is_null($credit_wallet)){
            throw new UserException('Wallet could not be credited');
        }
        $credit = static::findExisting(static::TYPE_CREDIT, $reference, $credit_wallet->id);
        if(!is_null($credit)){
            if(!$throwDup){
                return $credit;
            }
            throw new InvalidCallException('Duplicate Transaction');
        }
        $credit = new static();
        $credit->setAttributes([
            'type' => static::TYPE_CREDIT,
            'wallet_id' => $credit_wallet->id,
            'value' => $value,
            'reference' => $reference,
            'narration' => $narration?:'Credit received',
            'tag' => 'credit'
        ]);
        if(!$credit->save(false)){
            throw new UserException('Wallet could not be credited');
        }
        return $credit;
    }

    public static function negateType($type){
        if($type === SELF::TYPE_CREDIT){
            return SELF::TYPE_DEBIT;
        }
        if($type === SELF::TYPE_DEBIT){
            return SELF::TYPE_CREDIT;
        }
        throw new InvalidCallException('Invalid transaction type');
    } 

    public static function reverse(string $reference){
        $isolated = false;
        $db = static::getDb();
        $transaction  = $db->getTransaction();
        if(is_null($transaction)){
            $isolated = true;
            $transaction = $db->beginTransaction(\yii\db\Transaction::SERIALIZABLE);
        }
        try {
            $txns = static::find()->where(['reference' => $reference])->with(['wallet'])->all();
            if(count($txns) == 0){
                throw new NotFoundHttpException('No transactions were found with this reference');
            }
            $noreverse = WalletModule::getPsoParam('wallet.non_reversible_transaction_tags',[]);
            $count = 0;
            foreach($txns as $txn){
                if(in_array($txn->tag, $noreverse)){
                    throw new UserException('Some transactions have irreversible tags');
                }
                $reverse_reference = "$reference-RVSL";
                $reverse = new WalletInstruction(SELF::negateType($txn->type));
                $reverse->setWallet($txn->wallet);
                $reverse->setAttributes([
                    'value' => abs($txn->value),
                    'reference' => $reverse_reference,
                    'narration' => isset($txn->narration)?"$txn->narration/Reversal":$reverse_reference,
                    'tag' => SELF::TAG_REVERSAL
                ]);
                if(!$reverse->send()){
                    throw new ErrorException("Transaction with reference $reference and id $txn->id could not be reversed");
                }
                $count++;
            }
            if($isolated){
                $transaction->commit();
            }
            return $count;
        } catch (\Throwable $th) {
            if($isolated){
                $transaction->rollBack();
            }
            throw $th;
        }
    }
}
