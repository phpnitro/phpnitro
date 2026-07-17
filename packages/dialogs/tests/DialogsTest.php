<?php

namespace Engine\Dialogs\Tests;

use Engine\Dialogs\AlertButton;
use Engine\Dialogs\ConfirmButton;
use PHPUnit\Framework\TestCase;

final class DialogsTest extends TestCase
{
    public function testAlertButtonEscapesMessageAsAJsonJsString(): void
    {
        $html = AlertButton::make('It\'s "done"', title: 'Info')->render();

        $this->assertStringContainsString(
            'phpxDialogs.alert(' . htmlspecialchars(json_encode("It's \"done\"", JSON_THROW_ON_ERROR), ENT_QUOTES) . ', ',
            $html,
        );
        $this->assertStringContainsString(htmlspecialchars(json_encode('Info', JSON_THROW_ON_ERROR), ENT_QUOTES), $html);
    }

    public function testConfirmButtonCallsSubmitActionOnlyInsideTheConfirmCallback(): void
    {
        $html = ConfirmButton::make('Vraiment supprimer ?', action: 'deleteItem', label: 'Supprimer')->render();

        $this->assertStringContainsString('phpxDialogs.confirm(', $html);
        $this->assertStringContainsString('window.phpxNav.submitAction(', $html);
        $this->assertStringContainsString(
            htmlspecialchars(json_encode('deleteItem', JSON_THROW_ON_ERROR), ENT_QUOTES),
            $html,
        );
        $this->assertStringContainsString('Supprimer', $html);
    }
}
