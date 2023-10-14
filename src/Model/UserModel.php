<?php
declare(strict_types=1);
namespace Zodream\Database\Model;

use Zodream\Infrastructure\Contracts\UserObject;

abstract class UserModel extends Model implements UserObject {

    public static function getRememberTokenName(): string {
        return 'remember_token';
    }

    public static function getIdentityName(): string {
        return 'id';
    }
    
    public function getIdentity(): int|string {
        return $this->getAttribute(static::getIdentityName());
    }

    /**
     * @return string
     */
    public function getRememberToken(): string {
        return $this->getAttribute(static::getRememberTokenName());
    }

    /**
     * @param string $token
     * @return static
     */
    public function setRememberToken(string $token): static {
        $this->setAttribute(static::getRememberTokenName(), $token);
        $this->save();
        return $this;
    }

    public function login($remember = false): void {
        $this->invoke(static::BEFORE_LOGIN, [$this]);
        auth()->login($this, $remember);
        $this->invoke(static::AFTER_LOGIN, [$this]);
    }
    
    public function logout(): void {
        $this->invoke(static::BEFORE_LOGOUT, [$this]);
        auth()->logout();
        $this->invoke(static::AFTER_LOGOUT, [$this]);
    }

    /**
     * 根据 记住密码 token 获取用户
     * @param int|string $id
     * @param string $token
     * @return UserModel|null
     * @throws \Exception
     */
    public static function findByRememberToken(int|string $id, string $token): ?static {
        return static::find([
            static::getRememberTokenName() => $token,
            static::getIdentityName() => $id
        ]);
    }

    public static function findByAccount(string $username, string $password): ?static {
        throw new \Exception('undefined method');
    }

    public static function findByIdentity(int|string $id): ?static {
        return static::find($id);
    }

    public static function findByToken(string $token): ?static {
        throw new \Exception('undefined method');
    }

}