<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AndarmaSiteContent extends Model {
    protected $table = 'andarma_site_contents';
    protected $fillable = ['content_key', 'content_value'];
}
