<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VolunterProfile extends Model
{
    protected $fillable = [
        'user_id',
        'skills',
        'availability',
        'domain',
        'total_hours',
        'previous_voluntering',
        'previous_work_place',
        'car',
        'experience_years',
        'Commitment_type',
        'Favorite_period',
        'Educational_level',
        'bio',
        'facebook',
        'linkedin',
    ];
    protected function casts(): array
    {
        return [
            'availability' => 'array',
            'car' => 'boolean',
            'previous_voluntering' => 'boolean',
        ];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
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
        return $this->belongsToMany(Skill::class, 'volunteer_skills');
    }
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'volunteer_categories');
    }
    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'volunter_profile_id');
    }
}
