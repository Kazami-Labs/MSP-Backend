<?php

namespace App;

use App\Events\UserCreating;
use App\Post;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use Notifiable;

    const IS_ADMIN_YES = 1;
    const IS_ADMIN_NO  = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'nicename',
        'email',
        'password',
        'is_admin',
        'status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'avatar',
        'password',
        'remember_token',
    ];

    /**
     * 此模型的事件映射.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'creating' => UserCreating::class,
    ];

    protected $appends = ['avatar_addr'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function scopeSearchSelect($query)
    {
        return $query->select('id', 'nicename', 'avatar');
    }

    public function scopeSearchCondition($query, $searchUser)
    {
        return $query->where('name', 'like', "%{$searchUser}%")
            ->whereOr('email', 'like', "%{$searchUser}%")
            ->whereOr('nicename', 'like', "%{$searchUser}%")
            ->whereOr('email', 'like', "%{$searchUser}%");
    }

    public function getAvatarAddrAttribute()
    {
        $url = '';
        if (empty($this->avatar)) {
            $url = gravatar($this->email, 'large');
        } elseif (filter_var($this->avatar, FILTER_VALIDATE_URL) === false) {
            $url = route('user.avatar', ['id' => $this->id]);
        } else {
            $url = $this->avatar;
        }
        return $url;
    }

    public function setPasswordAttribute(string $password)
    {
        if (is_null($password) || $password === '') {
            return;
        }
        $this->attributes['password'] = Hash::make($password);
    }

    public function setIsAdminAttribute(int $isAdmin)
    {
        $this->attributes['is_admin'] = in_array($isAdmin, [self::IS_ADMIN_YES, self::IS_ADMIN_NO]) ?
        $isAdmin : self::IS_ADMIN_NO;
    }

    /**
     * Token 登记
     *
     * @param integer $ttl $token 有效期（分钟）
     * @param string $token 可选，不传会生成 token
     * @return string 返回 token （无论有没有定义 $token 参数）
     * @author Tsukasa Kanzaki <tsukasa.kzk@gmail.com>
     * @datetime 2019-05-11
     */
    public function setToken(int $ttl, string $token = null): string
    {
        /**
         * token 为空
         * 则优先从 Redis 中查找，没有再生成
         */
        if (is_null($token)) {
            $token = Cache::store('redis')->get("user:id:{$this->id}");
            if (is_null($token)) {
                $token = md5($this->toJson() . time() . rand(10, 99));
            }
        }

        if ($ttl > 0) {
            Cache::store('redis')->set(
                "user:token:{$token}",
                $this->id,
                60 * $ttl
            );
            Cache::store('redis')->set(
                "user:id:{$this->id}",
                $token,
                60 * $ttl
            );
        } else {
            Cache::store('redis')->set("user:token:{$token}", $this->id);
            Cache::store('redis')->set("user:id:{$this->id}", $token);
        }
        return $token;
    }

    /**
     * Token 注销
     *
     * @return void
     * @author Tsukasa Kanzaki <tsukasa.kzk@gmail.com>
     * @datetime 2019-05-11
     */
    public function destroyToken()
    {
        // 移除相应的键
        $token = Cache::store('redis')->get("user:id:{$this->id}");
        if ($token) {
            Cache::store('redis')->forget("user:token:{$token}");
        }
        Cache::store('redis')->forget("user:id:{$this->id}");
    }

    /**
     * 获取用户信息 (by token)
     * @param string $token
     * @return User|null
     */
    public static function findWithToken(string $token)
    {
        $userId = Cache::store('redis')->get("user:token:{$token}");
        if (is_null($userId)) {
            return null;
        }
        return self::find($userId);
    }

}
