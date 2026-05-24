<?php

namespace LoggedCloud\PageStudio\Templates\Builtin;

use LoggedCloud\PageStudio\Templates\Template;

class PricingTemplate extends Template
{
    public static function name(): string  { return 'pricing'; }
    public static function label(): string { return 'Pricing page'; }

    public static function description(): string
    {
        return 'A static /pricing page with a three-tier card row · starter, team, and business.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'pricing',
            'method'        => 'GET',
            'path_template' => '/pricing',
            'description'   => 'Pricing page · three tiers',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal', 'literal_value' => 'pricing'],
            ],
        ];
    }

    public static function blocks(): array
    {
        $tiers = self::block('columns-3', ['gap' => 'md']);
        $tiers['children'] = [
            'left'   => [
                self::block('card', ['title' => 'Starter', 'subtitle' => '£0 / month', 'tone' => 'neutral']),
                self::block('heading',   ['text' => 'Starter', 'level' => 'h3']),
                self::block('paragraph', ['text' => 'For hobby projects. Includes 1 user, 100 pages, community support.']),
                self::block('button',    ['label' => 'Get started', 'href' => '/sign-up', 'variant' => 'secondary']),
            ],
            'middle' => [
                self::block('card', ['title' => 'Team', 'subtitle' => '£12 / user / month', 'tone' => 'info']),
                self::block('heading',   ['text' => 'Team', 'level' => 'h3']),
                self::block('paragraph', ['text' => 'For growing teams. Includes 10 users, 5,000 pages, email support.']),
                self::block('button',    ['label' => 'Start trial', 'href' => '/sign-up', 'variant' => 'primary']),
            ],
            'right'  => [
                self::block('card', ['title' => 'Business', 'subtitle' => 'From £499 / month', 'tone' => 'success']),
                self::block('heading',   ['text' => 'Business', 'level' => 'h3']),
                self::block('paragraph', ['text' => 'For larger orgs. Unlimited users, SSO, audit log, priority support.']),
                self::block('button',    ['label' => 'Talk to sales', 'href' => '/contact', 'variant' => 'ghost']),
            ],
        ];

        return [
            self::block('heading',   ['text' => 'Simple, honest pricing', 'level' => 'h1', 'align' => 'center']),
            self::block('paragraph', ['text' => 'Pick the tier that fits today, change it whenever you like.']),
            $tiers,
        ];
    }
}
