<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Device;

/**
 * Composes an email in the user's own mail app via Intent.ACTION_SENDTO
 * (a "mailto:" intent, so it only ever matches real mail apps — unlike
 * ACTION_SEND, which would also list unrelated share targets). An
 * action-string builder, not a widget: attach EmailSender::composeAction()
 * to any Button.
 *
 * Fire-and-forget: there's no result field — same as UrlLauncher, there's
 * no meaningful "it sent" signal from here, only "the mail app opened
 * with a draft," the actual send is the user's own action from there.
 */
final class EmailSender
{
    public static function composeAction(string $to, string $subject = '', string $body = ''): string
    {
        return 'device:sendemail:' . rawurlencode($to) . ':' . rawurlencode($subject) . ':' . rawurlencode($body);
    }
}
