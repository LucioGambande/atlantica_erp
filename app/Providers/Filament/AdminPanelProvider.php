<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Atlantica Terranova')
            ->brandLogo(asset('images/brand/atlantica-terranova-logo.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/brand/atlantica-terranova-logo.png'))
            ->colors([
                'primary' => Color::Blue,
                'danger' => Color::Red,
                'warning' => Color::Red,
                'success' => Color::Sky,
                'info' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString(
                    <<<'CSS'
<style>
    /* Cabeceras neutras y separadas del menú (evita mezcla visual con el azul primario) */
    .fi-panel-admin .fi-sidebar-header {
        background-color: #f8fafc !important;
        border-bottom: 1px solid rgb(15 23 42 / 0.08) !important;
    }
    .dark .fi-panel-admin .fi-sidebar-header {
        background-color: #0f172a !important;
        border-bottom: 1px solid rgb(255 255 255 / 0.1) !important;
    }
    .fi-panel-admin .fi-topbar nav {
        background-color: #f8fafc !important;
        border-bottom: 1px solid rgb(15 23 42 / 0.08) !important;
    }
    .dark .fi-panel-admin .fi-topbar nav {
        background-color: #0f172a !important;
        border-bottom: 1px solid rgb(255 255 255 / 0.1) !important;
    }
    /*
     * Filament pone la clase fi-logo en el propio <img>, no en un contenedor.
     * Loseta siempre clara para que el PNG oscuro se lea en claro y en oscuro.
     */
    .fi-panel-admin img.fi-logo,
    .fi-panel-admin .fi-simple-header img.fi-logo {
        background-color: #ffffff !important;
        padding: 0.5rem 0.85rem !important;
        border-radius: 0.5rem !important;
        box-sizing: content-box !important;
        box-shadow:
            0 0 0 1px rgb(15 23 42 / 0.12),
            0 2px 8px rgb(15 23 42 / 0.08) !important;
    }
    .dark .fi-panel-admin img.fi-logo,
    .dark .fi-panel-admin .fi-simple-header img.fi-logo {
        background-color: #f1f5f9 !important;
        box-shadow:
            0 0 0 1px rgb(255 255 255 / 0.2),
            0 2px 10px rgb(0 0 0 / 0.35) !important;
    }
    .fi-panel-admin div.fi-logo {
        background-color: #ffffff;
        padding: 0.5rem 0.85rem;
        border-radius: 0.5rem;
        box-shadow:
            0 0 0 1px rgb(15 23 42 / 0.12),
            0 2px 8px rgb(15 23 42 / 0.08);
    }
    .dark .fi-panel-admin div.fi-logo {
        background-color: #f1f5f9;
        box-shadow:
            0 0 0 1px rgb(255 255 255 / 0.2),
            0 2px 10px rgb(0 0 0 / 0.35);
    }

    /* Pedidos: líneas en una fila; eliminar alineado a la derecha */
    .order-items-one-line.fi-fo-repeater > ul > .fi-fo-repeater-item {
        display: flex;
        flex-direction: row;
        align-items: flex-end;
        gap: 0.5rem;
        flex-wrap: nowrap;
        border-radius: 0.375rem !important;
        box-shadow: none !important;
        background: transparent !important;
        border: 1px solid rgb(15 23 42 / 0.08) !important;
    }
    .dark .order-items-one-line.fi-fo-repeater > ul > .fi-fo-repeater-item {
        border-color: rgb(255 255 255 / 0.1) !important;
    }
    .order-items-one-line .fi-fo-repeater-item-header {
        order: 2;
        flex: 0 0 auto;
        padding: 0 0.5rem 0.375rem 0 !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
    }
    .order-items-one-line .fi-fo-repeater-item-header ul {
        margin-inline-start: 0 !important;
    }
    .order-items-one-line .fi-fo-repeater-item-content {
        order: 1;
        flex: 1 1 auto;
        min-width: 0;
        padding: 0.5rem 0 0.5rem 0.75rem !important;
        border: none !important;
    }
    .order-items-one-line .fi-fo-repeater-item-content .grid {
        gap: 0.5rem 0.75rem;
    }
    .order-items-one-line .fi-fo-repeater-item > * {
        border-top-width: 0 !important;
        border-bottom-width: 0 !important;
    }
    @media (max-width: 1280px) {
        .order-items-one-line.fi-fo-repeater > ul > .fi-fo-repeater-item {
            flex-wrap: wrap;
        }
        .order-items-one-line .fi-fo-repeater-item-header {
            order: 3;
            width: 100%;
            display: flex;
            justify-content: flex-end;
            padding-bottom: 0.5rem !important;
        }
    }
</style>
CSS
                ),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
