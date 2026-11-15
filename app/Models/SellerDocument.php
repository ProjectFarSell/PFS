<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['file_path'];

    public function application()
    {
        return $this->belongsTo(SellerApplication::class, 'seller_application_id');
    }
}
