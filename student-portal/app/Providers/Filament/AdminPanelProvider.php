<?php

namespace App\Providers\Filament;

use App\Filament\Pages\LearningManagement;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->homeUrl(fn (): string => LearningManagement::getUrl())
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(fn () => redirect()->route('login'))
            ->brandName('ВЭШ — Управление обучением')
            ->brandLogo(fn () => view('filament.brand'))
            ->font('Inter', url: asset('brand/fonts.css'), provider: LocalFontProvider::class)
            ->darkMode(false)
            ->navigation(false)
            ->globalSearch(false)
            ->maxContentWidth(Width::Full)
            ->renderHook(PanelsRenderHook::PAGE_HEADER_WIDGETS_BEFORE, fn () => view('filament.workspace-tabs'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.workspace-scripts'))
            ->colors([
                'primary' => array_map(Color::convertToOklch(...), [
                    50 => '#f1f7fa', 100 => '#e6eff3', 200 => '#c9dde6',
                    300 => '#9dbfce', 400 => '#629bb3', 500 => '#327e9e',
                    600 => '#226d8d', 700 => '#1c526d', 800 => '#1c3948',
                    900 => '#182f3c', 950 => '#10212b',
                ]),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
