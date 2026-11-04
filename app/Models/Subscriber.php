<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** An email address signed up for updates through the footer form. */
#[Fillable(['email', 'source'])]
#[Hidden(['ip_address'])]
class Subscriber extends Model {}
