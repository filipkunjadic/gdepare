<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['description', 'amount', 'currency', 'date', 'payment_method', 'category', 'foreign_amount', 'foreign_currency'])]
#[Hidden(['user_id', 'created_at', 'updated_at', 'pivot'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Receiver, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Receiver::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /** @return array{amount: 'decimal:2', foreign_amount: 'decimal:2', date: 'date:Y-m-d'} */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'foreign_amount' => 'decimal:2', 'date' => 'date:Y-m-d'];
    }
}
