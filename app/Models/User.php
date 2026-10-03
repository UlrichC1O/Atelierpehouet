<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An account of the admin CMS (/admin): the only login of the site.
 *
 * is_admin (docs/CMS.md §13 D18) is the explicit right to use the admin (Gate "admin"). It is never
 * mass-assigned: set it with forceFill() / $user->is_admin = true, and only once the column exists
 * (a database whose migrations are pending has no such column).
 *
 * @property bool $is_admin
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Real booleans for Postgres with emulated prepares (see Media::inGallery()). A classic mutator:
     * an isAdmin() method returning an Attribute would read like a boolean check and always be truthy.
     */
    public function setIsAdminAttribute(mixed $value): void
    {
        $this->attributes['is_admin'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
