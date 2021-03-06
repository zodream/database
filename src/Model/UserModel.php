<?php
declare(strict_types=1);
namespace Zodream\Database\Model;

use Zodream\Infrastructure\Contracts\UserObject;

abstract class UserModel extends Model implements UserObject {

    public static function getRememberTokenName() {
        return 'remember_token';
    }

    public static function getIdentityName() {
        return 'id';
    }
    
    public function getIdentity() {
        return $this->getAttribute(static::getIdentityName());
    }

    /**
     * @return string
     */
    public function getRememberToken() {
        return $this->getAttribute(static::getRememberTokenName());
    }

    /**
     * @param string $token
     * @return static
     */
    public function setRememberToken($token) {
        $this->setAttribute(static::getRememberTokenName(), $token);
        $this->save();
        return $this;
    }

    public function login($remember = false) {
        $this->invoke(static::BEFORE_LOGIN, [$this]);
        auth()->login($this, $remember);
        $this->invoke(static::AFTER_LOGIN, [$this]);
        return true;
    }
    
    public function logout() {
        $this->invoke(static::BEFORE_LOGOUT, [$this]);
        auth()->logout();
        $this->invoke(static::AFTER_LOGOUT, [$this]);
        return true;
    }

    /**
     * 根据 记住密码 token 获取用户
     * @param integer $id
     * @param string $token
     * @return UserObject
     */
    public static function findByRememberToken($id, $token) {
        return static::find([
            static::getRememberTokenName() => $token,
            static::getIdentityName() => $id
        ]);
    }

    public static function findByAccount($username, $password) {
        throw new \Exception('undefined method');
    }

    public static function findByIdentity($id) {
        return static::find($id);
    }

    public static function findByToken($token) {
        throw new \Exception('undefined method');
    }

}