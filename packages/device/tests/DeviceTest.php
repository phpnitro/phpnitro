<?php

namespace Engine\Device\Tests;

use Engine\Device\Camera;
use Engine\Device\Fingerprint;
use Engine\Device\ImagePicker;
use Engine\Device\Microphone;
use Engine\Device\Notify;
use Engine\Device\Printer;
use Engine\Device\Share;
use Engine\Device\Sound;
use Engine\Device\Vibrate;
use PHPUnit\Framework\TestCase;

final class DeviceTest extends TestCase
{
    public function testVibrateOnClickDefaultsTo200Milliseconds(): void
    {
        $this->assertSame('phpxDevice.vibrate(200)', Vibrate::onClick());
    }

    public function testVibrateOnClickAcceptsCustomDuration(): void
    {
        $this->assertSame('phpxDevice.vibrate(300)', Vibrate::onClick(300));
    }

    public function testNotifyOnClickEscapesArgumentsAsJson(): void
    {
        $this->assertSame(
            'phpxDevice.notify("Title \"quoted\"", "Body")',
            Notify::onClick('Title "quoted"', 'Body'),
        );
    }

    public function testSoundOnClickEncodesUrl(): void
    {
        $this->assertSame('phpxDevice.playSound("\/assets\/audio\/beep.wav")', Sound::onClick('/assets/audio/beep.wav'));
    }

    public function testPrinterOnClickTakesNoArguments(): void
    {
        $this->assertSame('phpxDevice.print()', Printer::onClick());
    }

    public function testMicrophoneOnClickReferencesOutputId(): void
    {
        $this->assertSame("phpxDevice.openMicrophone('mic1')", Microphone::onClick('mic1'));
    }

    public function testMicrophoneOutputElementRendersSpanWithId(): void
    {
        $html = Microphone::outputElement('mic1')->render();

        $this->assertStringContainsString('id="mic1"', $html);
        $this->assertStringContainsString('<span', $html);
    }

    public function testFingerprintOnClickReferencesOutputId(): void
    {
        $this->assertSame("phpxDevice.fingerprint('fp1')", Fingerprint::onClick('fp1'));
    }

    public function testFingerprintOutputElementRendersSpanWithId(): void
    {
        $html = Fingerprint::outputElement('fp1')->render();

        $this->assertStringContainsString('id="fp1"', $html);
        $this->assertStringContainsString('<span', $html);
    }

    public function testCameraOpenAndCaptureTriggersReferenceElementIds(): void
    {
        $this->assertSame("phpxDevice.openCamera('vid1')", Camera::openOnClick('vid1'));
        $this->assertSame("phpxDevice.takeNativePhoto('img1')", Camera::captureOnClick('img1'));
    }

    public function testCameraVideoElementRendersVideoTagWithId(): void
    {
        $html = Camera::videoElement('vid1')->render();

        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('id="vid1"', $html);
    }

    public function testCameraImageElementRendersImgTagWithId(): void
    {
        $html = Camera::imageElement('img1')->render();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('id="img1"', $html);
    }

    public function testImagePickerPickOnClickReferencesBothIds(): void
    {
        $this->assertSame(
            "phpxDevice.pickImage('preview1', 'field1')",
            ImagePicker::pickOnClick('preview1', 'field1'),
        );
    }

    public function testImagePickerHiddenFieldRendersHiddenInput(): void
    {
        $html = ImagePicker::hiddenField('photo', 'field1')->render();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="photo"', $html);
        $this->assertStringContainsString('id="field1"', $html);
    }

    public function testImagePickerPreviewElementRendersImgTagWithId(): void
    {
        $html = ImagePicker::previewElement('preview1')->render();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('id="preview1"', $html);
    }

    public function testShareOnClickEncodesTextAndTitle(): void
    {
        $this->assertSame(
            'phpxDevice.share("Look at this", "Title \"quoted\"")',
            Share::onClick('Look at this', 'Title "quoted"'),
        );
    }

    public function testShareOnClickDefaultsTitleToEmptyString(): void
    {
        $this->assertSame('phpxDevice.share("x", "")', Share::onClick('x'));
    }
}
