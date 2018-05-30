<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace yuncms\article\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yuncms\tag\models\Tag;
use yuncms\user\models\User;
use yii\helpers\ArrayHelper;
use yuncms\collection\models\Collection;
use creocoder\taggable\TaggableBehavior;

/**
 * Class Article
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $title
 * @property string $sub_title
 * @property string $description
 * @property string $content
 * @property int $status
 * @property int $comments
 * @property int $supports
 * @property int $collections
 * @property int $views
 * @property boolean $is_top 是否置顶
 * @property boolean $is_best 是否推荐
 * @property int $created_at
 * @property int $updated_at
 * @property int $published_at
 *
 * @property int $category_id
 *
 * @property User $user
 *
 * @package yuncms\article\models
 */
class Article extends ActiveRecord
{
    //场景定义
    const SCENARIO_CREATE = 'create';//创建
    const SCENARIO_UPDATE = 'update';//更新

    //状态定义
    const STATUS_DRAFT = 'draft';//草稿
    const STATUS_REVIEW = 'review';//审核
    const STATUS_REJECTED = 'rejected';//拒绝
    const STATUS_PUBLISHED = 'published';//发布

    //事件定义
    const BEFORE_PUBLISHED = 'beforePublished';
    const AFTER_PUBLISHED = 'afterPublished';
    const BEFORE_REJECTED = 'beforeRejected';
    const AFTER_REJECTED = 'afterRejected';

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return new ArticleQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class
            ],
            'tag' => [
                'class' => TaggableBehavior::class,
                'tagValuesAsArray' => true,
                'tagRelation' => 'tags',
                'tagValueAttribute' => 'id',
                'tagFrequencyAttribute' => 'frequency',
            ],
            'blameable' => [
                'class' => BlameableBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'user_id',
                ],
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        return ArrayHelper::merge($scenarios, [
            static::SCENARIO_CREATE => ['title', 'content', 'sub_title'],
            static::SCENARIO_UPDATE => [],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title', 'content'], 'required'],
            [['title', 'sub_title', 'cover', 'description'], 'filter', 'filter' => 'trim'],
            ['is_top', 'boolean'],
            ['is_best', 'boolean'],
            [['is_best', 'is_top'], 'default', 'value' => false],
            ['status', 'default', 'value' => self::STATUS_REVIEW],
            ['status', 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_REJECTED, self::STATUS_PUBLISHED]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('yuncms', 'ID'),
            'title' => Yii::t('yuncms/article', 'Title'),
            'sub_title' => Yii::t('yuncms/article', 'Sub Title'),
            'description' => Yii::t('yuncms/article', 'Description'),
            'category_id' => Yii::t('yuncms/article', 'Category'),
            'cover' => Yii::t('yuncms/article', 'Cover'),
            'status' => Yii::t('yuncms/article', 'Status'),
            'comments' => Yii::t('yuncms/article', 'Comments'),
            'supports' => Yii::t('yuncms/article', 'Supports'),
            'collections' => Yii::t('yuncms/article', 'Collections'),
            'views' => Yii::t('yuncms/article', 'Views'),
            'is_top' => Yii::t('yuncms/article', 'Is Top'),
            'is_best' => Yii::t('yuncms/article', 'Is Best'),
            'content' => Yii::t('yuncms/article', 'Content'),
            'created_at' => Yii::t('yuncms/article', 'Created At'),
            'updated_at' => Yii::t('yuncms/article', 'Updated At'),
            'published_at' => Yii::t('yuncms/article', 'Published At'),
        ];
    }

    public function isActive()
    {
        return $this->status == static::STATUS_PUBLISHED;
    }

    /**
     * 是否是作者
     * @return bool
     */
    public function isAuthor()
    {
        return $this->user_id == Yii::$app->user->id;
    }

    /**
     * Category Relation
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(Category::className(), ['id' => 'category_id']);
    }

    /**
     * Tag Relation
     * @return \yii\db\ActiveQuery
     */
    public function getTags()
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])->viaTable('{{%article_tag}}', ['article_id' => 'id']);
    }

    /**
     * Collection Relation
     * @return \yii\db\ActiveQueryInterface
     */
    public function getCollections()
    {
        return $this->hasMany(Collection::class, ['model_id' => 'id'])->onCondition(['model' => static::class]);
    }

    /**
     * User Relation
     * @return \yii\db\ActiveQueryInterface
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Favorite Relation
     * @return \yii\db\ActiveQueryInterface
     */
    public function getCollection()
    {
        return $this->hasOne(Collection::class, ['model_id' => 'id'])->onCondition(['model' => get_class($this)]);
    }

    /**
     * 机器审核
     * @param int $id model id
     * @param string $suggestion the ID to be looked for
     * @return void
     */
    public static function review($id, $suggestion)
    {
        if (($model = static::findOne($id)) != null) {
            if ($suggestion == 'pass') {
                $model->setPublished();
            } elseif ($suggestion == 'block') {
                $model->setRejected('');
            } elseif ($suggestion == 'review') { //人工审核，不做处理
            }
        }
    }

    /**
     * 获取待审
     * @param int $id
     * @return string 待审核的内容字符串
     */
    public static function findReview($id)
    {
        if (($model = static::findOne($id)) != null) {
            return $model->content;
        }
        return null;
    }

    /**
     * 审核通过
     * @return int
     */
    public function setPublished()
    {
        $this->trigger(self::BEFORE_PUBLISHED);
        $rows = $this->updateAttributes(['status' => static::STATUS_PUBLISHED, 'published_at' => time()]);
        $this->trigger(self::AFTER_PUBLISHED);
        return $rows;
    }

    /**
     * 拒绝通过
     * @param string $failedReason 拒绝原因
     * @return int
     */
    public function setRejected($failedReason)
    {
        $this->trigger(self::BEFORE_REJECTED);
        $rows = $this->updateAttributes(['status' => static::STATUS_REJECTED, 'failed_reason' => $failedReason]);
        $this->trigger(self::AFTER_REJECTED);
        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%article}}';
    }

    public static function getStatusList()
    {
        return [
            self::STATUS_DRAFT => Yii::t('article', 'Status Draft'),
            self::STATUS_REVIEW => Yii::t('article', 'Status Review'),
            self::STATUS_REJECTED => Yii::t('article', 'Status Rejected'),
            self::STATUS_PUBLISHED => Yii::t('article', 'Status Published'),
        ];
    }

    /**
     * 保存后生成短网址
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            $this->updateAttributes(['uuid' => $this->generateKey()]);
            /* 用户文章数+1 */
            $this->user->extra->updateCounters(['articles' => 1]);
        }
        return parent::afterSave($insert, $changedAttributes);
    }

    /**
     * 生成key
     */
    protected function generateKey()
    {
        $result = sprintf("%u", crc32($this->id));
        $key = '';
        while ($result > 0) {
            $s = $result % 62;
            if ($s > 35) {
                $s = chr($s + 61);
            } elseif ($s > 9 && $s <= 35) {
                $s = chr($s + 55);
            }
            $key .= $s;
            $result = floor($result / 62);
        }
        return $key;
    }

    /**
     * 删除后
     */
    public function afterDelete()
    {
        /* 用户文章数-1 */
        $this->user->extra->updateCounters(['articles' => -1]);
        parent::afterDelete();
    }
}
