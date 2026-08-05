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
 * Restarts the app — relaunches its own launcher Intent with
 * FLAG_ACTIVITY_CLEAR_TASK, then kills the current process, so the next
 * launch is a genuinely fresh process (not just a re-created Activity
 * with the old process's state still around). An action-string builder,
 * not a widget: attach RestartApp::restartAction() to any Button.
 *
 * There is nothing to observe after this call — the process that would
 * report a result no longer exists by the time it would.
 */
final class RestartApp
{
    public static function restartAction(): string
    {
        return 'device:restartapp';
    }
}
