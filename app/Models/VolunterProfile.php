<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunterProfile extends Model
{
    protected $table = 'volunter_profiles';

    protected $fillable = [
        'user_id',
        'Favorite_period',
        'total_hours',
        'previous_voluntering',
        'previous_work_place',
        'experience_years',
        'car',
        'status',
        'bio',
        'Commitment_type',
        'Educational_level',
        'facebook',
        'linkedin',
        'points',
        'rank',

    ];

    protected $casts = [
        'car' => 'boolean',
        'previous_voluntering' => 'boolean',
    ];

    // ==================== العلاقات الأساسية ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==================== العلاقات مع نظام المتطوع ====================

    public function tasks()
    {
        return $this->hasMany(VolunteerTask::class, 'volunteer_id');
    }
    public function badges()
{
    return $this->hasMany(VolunteerBadge::class, 'volunteer_id');
}

    public function checkIns()
    {
        return $this->hasMany(VolunteerCheckIn::class, 'volunteer_id');
    }

    public function evaluations()
    {
        return $this->hasMany(VolunteerEvaluation::class, 'volunteer_id');
    }

    public function certificates()
    {
        return $this->hasMany(VolunteerCertificate::class, 'volunteer_id');
    }

    // ==================== العلاقات مع المراجع ====================

    public function domains()
    {
        return $this->belongsToMany(Domain::class, 'volunteer_domains');
    }

    public function days()
    {
        return $this->belongsToMany(Day::class, 'volunteer_days');
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'volunteer_languages');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'volunteer_skills', 'volunter_profile_id', 'skill_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'volunteer_categories');
    }
}
