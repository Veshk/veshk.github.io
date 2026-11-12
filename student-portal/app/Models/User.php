<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $attributes = ['role' => 'student', 'reviews_unassigned' => false];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'reviews_unassigned' => 'boolean', 'profile_photo_position' => 'integer'];
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'senior'], true);
    }

    public function isSenior(): bool
    {
        return $this->role === 'senior';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff();
    }

    public function studyGroups(): HasMany
    {
        return $this->hasMany(StudyGroup::class, 'administrator_id');
    }

    public function portalVisits(): HasMany
    {
        return $this->hasMany(PortalVisit::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(EnrollmentApplication::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function selfStudyEnrollments(): HasMany
    {
        return $this->hasMany(SelfStudyEnrollment::class);
    }

    public function courseOrders(): HasMany
    {
        return $this->hasMany(CourseOrder::class);
    }
}
