<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentProviderAccount extends Model
{
    use HasFactory;

    protected $table = 'payment_provider_accounts';

    protected $fillable = [
        'uuid',
        'user_id',
        'company_id',
        'seller_id',
        'provider',
        'display_name',
        'status',
        'mode',
        'config_source',
        'public_config',
        'capabilities',
        'last_verified_at',
        'metadata',
    ];

    protected $hidden = [
        'encrypted_credentials',
    ];

    protected $casts = [
        'public_config' => 'array',
        'capabilities' => 'array',
        'last_verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            $account->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Optional additive company scope; user ownership remains mandatory.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'provider_account_id');
    }

    public function setCredentials(array $credentials): void
    {
        $this->assertCredentialsAreSafe($credentials);
        $this->encrypted_credentials = Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR));
    }

    public function getCredentials(): array
    {
        if (! $this->encrypted_credentials) {
            return [];
        }

        return (array) json_decode(
            Crypt::decryptString($this->encrypted_credentials),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    public function getMaskedCredentials(): array
    {
        return $this->mask($this->getCredentials());
    }

    private function assertCredentialsAreSafe(array $credentials): void
    {
        $forbidden = ['card_number', 'pan', 'cvv', 'cvc', 'security_code'];

        foreach ($credentials as $key => $value) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                throw new InvalidArgumentException('provider_credentials_contain_payment_data');
            }

            if (is_array($value)) {
                $this->assertCredentialsAreSafe($value);

                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('provider_credentials_invalid');
            }
        }
    }

    private function mask(array $credentials): array
    {
        foreach ($credentials as $key => $value) {
            if (is_array($value)) {
                $credentials[$key] = $this->mask($value);

                continue;
            }

            $string = (string) $value;
            $credentials[$key] = $string === ''
                ? ''
                : str_repeat('*', max(8, min(strlen($string), 16))).substr($string, -4);
        }

        return $credentials;
    }
}
