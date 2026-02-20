# Map of resource file names to their permission keys
$resourcePermissions = @{
    "GroupCategoryResource.php"     = "manage-group-categories"
    "GroupFieldResource.php"        = "manage-group-fields"
    "GroupResource.php"             = "manage-groups"
    "GroupSubCategoryResource.php"  = "manage-group-sub-categories"
    "JobCategoryResource.php"       = "manage-job-categories"
    "JobResource.php"               = "manage-jobs"
    "LanguageKeyResource.php"       = "manage-language-keys"
    "LanguageResource.php"          = "manage-languages"
    "OnlineUsersResource.php"       = "manage-online-users"
    "OrderResource.php"             = "manage-orders"
    "PageCategoryResource.php"      = "manage-page-categories"
    "PageFieldResource.php"         = "manage-page-fields"
    "PageResource.php"              = "manage-pages"
    "PageSubCategoryResource.php"   = "manage-page-sub-categories"
    "PostReactionResource.php"      = "manage-post-reactions"
    "PostResource.php"              = "manage-posts"
    "ProductCategoryResource.php"   = "manage-product-categories"
    "ProductFieldResource.php"      = "manage-product-fields"
    "ProductResource.php"           = "manage-products"
    "ProductSubCategoryResource.php"= "manage-product-sub-categories"
    "ProfileFieldResource.php"      = "manage-profile-fields"
    "ReviewResource.php"            = "manage-reviews"
    "UserStoriesResource.php"       = "manage-user-stories"
    "UsersInvitationResource.php"   = "manage-invitations"
    "VerificationRequestsResource.php" = "manage-verification-requests"
}

$basePath = "e:\Personal\ouptel-admin\app\Filament\Admin\Resources"

foreach ($file in $resourcePermissions.Keys) {
    $filePath = Join-Path $basePath $file
    $permKey = $resourcePermissions[$file]

    if (-not (Test-Path $filePath)) {
        Write-Host "SKIP (not found): $file"
        continue
    }

    $content = Get-Content $filePath -Raw

    if ($content -match "HasPanelAccess") {
        Write-Host "SKIP (already has trait): $file"
        continue
    }

    # 1. Add use statement after the Pages use statement
    $content = $content -replace "(use App\\Filament\\Admin\\Resources\\\\[A-Za-z]+\\\\Pages;)", "`$1`nuse App\Filament\Admin\Concerns\HasPanelAccess;"

    # 2. Add trait + permissionKey after 'class XxxResource extends Resource\n{'
    $content = $content -replace "(class \w+ extends Resource\s*\{)", "`$1`n    use HasPanelAccess;`n`n    protected static string `$permissionKey = '$permKey';"

    Set-Content $filePath $content -NoNewline
    Write-Host "DONE: $file -> $permKey"
}

Write-Host "`nAll done!"
