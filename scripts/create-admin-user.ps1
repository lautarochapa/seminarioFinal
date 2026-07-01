param(
    [string] $Email    = "admin@cccontrol.test",
    [string] $Password = "password123",
    [string] $Username = "admin",
    [string] $Name     = "Admin",
    [string] $Lastname = "Test"
)

$ErrorActionPreference = "Stop"

$php  = 'C:\xampp\php74\php.exe'
$root = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path $php)) {
    Write-Host "[ERROR] PHP 7.4 no encontrado en $php" -ForegroundColor Red
    exit 1
}

$script = @"
<?php
chdir('$($root.Replace("\","\\"))');
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$now = now();
Illuminate\Support\Facades\DB::table('users')->updateOrInsert(
    ['email' => '$Email'],
    [
        'name'              => '$Name',
        'lastname'          => '$Lastname',
        'username'          => '$Username',
        'password'          => Illuminate\Support\Facades\Hash::make('$Password'),
        'status'            => 'active',
        'nivel_acceso'      => 1,
        'email_verified_at' => \$now,
        'updated_at'        => \$now,
        'created_at'        => \$now,
        'deleted_at'        => null,
    ]
);
\$userId = Illuminate\Support\Facades\DB::table('users')->where('email', '$Email')->value('id');
\$roleId = Illuminate\Support\Facades\DB::table('roles')->where('code', 'super_admin')->value('id');
if (\$userId && \$roleId) {
    Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
        ['user_id' => \$userId, 'role_id' => \$roleId],
        ['created_at' => \$now]
    );
}
echo 'Usuario listo: $Email' . PHP_EOL;
"@

$tmpFile = [System.IO.Path]::GetTempFileName() + ".php"
[System.IO.File]::WriteAllText($tmpFile, $script, [System.Text.Encoding]::UTF8)

try {
    Push-Location $root
    & $php $tmpFile
} finally {
    Pop-Location
    Remove-Item $tmpFile -ErrorAction SilentlyContinue
}
