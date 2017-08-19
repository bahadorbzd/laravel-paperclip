<?php
namespace Czim\Paperclip\Test\Integration;

use Czim\FileHandling\Handler\FileHandler;
use Czim\Paperclip\Attachment\Attachment;
use Czim\Paperclip\Test\Helpers\VariantStrategies\TestTextToHtmlStrategy;
use Czim\Paperclip\Test\ProvisionedTestCase;
use Illuminate\Database\Eloquent\Model;
use SplFileInfo;

class PaperclipBasicAttachmentTest extends ProvisionedTestCase
{

    /**
     * @test
     */
    function it_processes_and_stores_a_new_file()
    {
        $model = $this->getTestModel();

        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->save();

        $processedFilePath = $this->getUploadedAttachmentPath($model);

        static::assertInstanceOf(Attachment::class, $model->attachment);
        static::assertEquals('source.txt', $model->attachment_file_name);
        static::assertFileExists($processedFilePath, "File was not stored");

        static::assertEquals('source.txt', $model->attachment_file_name);
        static::assertEquals(29, $model->attachment_file_size);
        static::assertEquals('text/plain', $model->attachment_content_type);

        static::assertEquals(
            'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/attachment/original/source.txt',
            $model->attachment->url()
        );

        if (file_exists($processedFilePath)) {
            unlink($processedFilePath);
        }
    }

    /**
     * @test
     */
    function it_removes_a_previously_attached_file()
    {
        $model = $this->getTestModel();

        // Store an initial file
        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->save();

        $processedFilePath = $this->getUploadedAttachmentPath($model);

        static::assertEquals('source.txt', $model->attachment_file_name);
        static::assertFileExists($processedFilePath, "File was not stored");

        // Remove it
        $model->attachment = Attachment::NULL_ATTACHMENT;
        $model->save();

        static::assertNull($model->attachment_file_name);
        static::assertNull($model->attachment_file_size);
        static::assertNull($model->attachment_content_type);
        static::assertNull($model->attachment_updated_at);
        static::assertFileNotExists($processedFilePath, "File was not removed");
    }

    /**
     * @test
     */
    function it_returns_model_attributes_including_attachments()
    {
        $model = $this->getTestModel();

        // Store an initial file
        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->save();

        $processedFilePath = $this->getUploadedAttachmentPath($model);

        $attributes = $model->getAttributes();

        static::assertArrayHasKey('attachment', $attributes);
        static::assertArrayHasKey('image', $attributes);
        static::assertSame($attributes['attachment'], $model->attachment);
        static::assertSame($attributes['image'], $model->image);

        if (file_exists($processedFilePath)) {
            unlink($processedFilePath);
        }
    }

    /**
     * @test
     * @depends it_processes_and_stores_a_new_file
     */
    function it_processes_and_stores_a_file_replacing_an_existing_attached_file()
    {
        $model = $this->getTestModel();

        // Store an initial file
        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->save();

        $processedFilePath = $this->getUploadedAttachmentPath($model);

        static::assertEquals('source.txt', $model->attachment_file_name);
        static::assertFileExists($processedFilePath, "File was not stored");

        // Overwrite with a new file
        $model->attachment = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        static::assertFileNotExists($processedFilePath, "Previous file was not removed");

        $processedFilePath = $this->getUploadedAttachmentPath($model, 'empty.gif');
        static::assertFileExists($processedFilePath, "New file was not stored");

        static::assertEquals('empty.gif', $model->attachment_file_name);
        static::assertEquals(42, $model->attachment_file_size);
        static::assertEquals('image/gif', $model->attachment_content_type);

        static::assertEquals(
            'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/attachment/original/empty.gif',
            $model->attachment->url()
        );

        if (file_exists($processedFilePath)) {
            unlink($processedFilePath);
        }
    }

    /**
     * @test
     */
    function it_processes_two_attachments_at_the_same_time()
    {
        $model = $this->getTestModel();

        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->image      = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        $processedFilePathOne     = $this->getUploadedAttachmentPath($model);
        $processedFilePathTwo     = $this->getUploadedAttachmentPath($model, 'empty.gif', 'image');
        $processedFilePathVariant = $this->getUploadedAttachmentPath($model, 'empty.gif', 'image', 'medium');

        static::assertInstanceOf(Attachment::class, $model->attachment);
        static::assertInstanceOf(Attachment::class, $model->image);

        static::assertEquals('source.txt', $model->attachment_file_name);
        static::assertEquals('empty.gif', $model->image_file_name);

        static::assertFileExists($processedFilePathOne, "File 1 was not stored");
        static::assertFileExists($processedFilePathTwo, "File 2 was not stored");
        static::assertFileExists($processedFilePathVariant, "File 2 variant was not stored");

        static::assertEquals('source.txt', $model->attachment_file_name);
        static::assertEquals(29, $model->attachment_file_size);
        static::assertEquals('text/plain', $model->attachment_content_type);

        static::assertEquals('empty.gif', $model->image_file_name);
        static::assertEquals(42, $model->image_file_size);
        static::assertEquals('image/gif', $model->image_content_type);

        static::assertEquals(
            'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/attachment/original/source.txt',
            $model->attachment->url()
        );
        static::assertEquals(
            'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/original/empty.gif',
            $model->image->url()
        );
        static::assertEquals(
            'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/medium/empty.gif',
            $model->image->url('medium')
        );

        if (file_exists($processedFilePathOne)) {
            unlink($processedFilePathOne);
        }
        if (file_exists($processedFilePathTwo)) {
            unlink($processedFilePathTwo);
        }
        if (file_exists($processedFilePathVariant)) {
            unlink($processedFilePathVariant);
        }
    }

    /**
     * @test
     * @depends it_processes_two_attachments_at_the_same_time
     */
    function it_deletes_processed_attachments_when_deleting_a_model()
    {
        $model = $this->getTestModel();

        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->image      = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        $processedFilePathOne     = $this->getUploadedAttachmentPath($model);
        $processedFilePathTwo     = $this->getUploadedAttachmentPath($model, 'empty.gif', 'image');
        $processedFilePathVariant = $this->getUploadedAttachmentPath($model, 'empty.gif', 'image', 'medium');

        $model->delete();

        static::assertFileNotExists($processedFilePathOne, "File 1 was not deleted");
        static::assertFileNotExists($processedFilePathTwo, "File 2 was not deleted");
        static::assertFileNotExists($processedFilePathVariant, "File 2 variant was not deleted");
    }

    /**
     * @test
     * @depends it_deletes_processed_attachments_when_deleting_a_model
     */
    function it_returns_paths_for_a_given_attachment()
    {
        $model = $this->getTestModel();

        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->image      = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        $paths = $model->pathsForAttachment('image');

        static::assertEquals(['original', 'medium'], array_keys($paths));
        static::assertEquals([
            'original' => 'Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/original/empty.gif',
            'medium'   => 'Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/medium/empty.gif',
        ], $paths);

        $paths = $model->pathsForAttachment('attachment');

        static::assertEquals(['original'], array_keys($paths));
        static::assertEquals([
            'original' => 'Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/attachment/original/source.txt',
        ], $paths);

        $model->delete();
    }

    /**
     * @test
     * @depends it_deletes_processed_attachments_when_deleting_a_model
     */
    function it_returns_urls_for_a_given_attachment()
    {
        $model = $this->getTestModel();

        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->image      = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        $paths = $model->urlsForAttachment('image');

        static::assertEquals(['original', 'medium'], array_keys($paths));
        static::assertEquals([
            'original' => 'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/original/empty.gif',
            'medium'   => 'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/medium/empty.gif',
        ], $paths);

        $paths = $model->urlsForAttachment('attachment');

        static::assertEquals(['original'], array_keys($paths));
        static::assertEquals([
            'original' => 'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/attachment/original/source.txt',
        ], $paths);

        $model->delete();
    }

    /**
     * @test
     * @depends it_deletes_processed_attachments_when_deleting_a_model
     */
    function its_attachments_can_be_serialized_for_json()
    {
        $model = $this->getTestModel();

        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->image      = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();

        static::assertEquals([
            'original' => [
                'path' => 'Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/attachment/original/source.txt',
                'url'  => 'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/attachment/original/source.txt',
            ],
        ], $model->attachment->jsonSerialize());

        static::assertEquals([
            'original' => [
                'path' => 'Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/original/empty.gif',
                'url'  => 'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/original/empty.gif',
            ],
            'medium' => [
                'path' => 'Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/medium/empty.gif',
                'url'  => 'http://localhost/paperclip/Czim/Paperclip/Test/Helpers/Model/TestModel/000/000/001/image/medium/empty.gif',
            ],
        ], $model->image->jsonSerialize());

        $model->delete();
    }

    /**
     * @test
     */
    function its_attachments_can_be_told_to_destroy_stored_variant_files()
    {
        $model = $this->getTestModel();

        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->image      = new SplFileInfo($this->getTestFilePath('empty.gif'));
        $model->save();


        $processedFilePathOne     = $this->getUploadedAttachmentPath($model);
        $processedFilePathTwo     = $this->getUploadedAttachmentPath($model, 'empty.gif', 'image');
        $processedFilePathVariant = $this->getUploadedAttachmentPath($model, 'empty.gif', 'image', 'medium');

        $model->attachment->destroy();

        static::assertFileNotExists($processedFilePathOne);

        $model->image->destroy(['medium']);

        static::assertFileNotExists($processedFilePathVariant, 'Destroyed variant file not deleted');
        static::assertFileExists($processedFilePathTwo, 'Unlisted original file should not have been deleted');

        $model->image->destroy();

        static::assertFileNotExists($processedFilePathTwo);
    }



    // ------------------------------------------------------------------------------
    //      Variants special column
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_stores_variants_data_on_model_if_configured_to_and_required_due_to_processing()
    {
        $this->app['config']->set('paperclip.variants.aliases.test-html', TestTextToHtmlStrategy::class);

        $model = $this->getTestModelWithAttachmentConfig([
            'attributes' => [
                'variants' => true,
            ],
            'variants' => [
                'test' => [
                    'test-html' => [],
                ],
            ],
        ]);

        $model->attachment = new SplFileInfo($this->getTestFilePath());
        $model->save();

        static::assertEquals([
            'test' => [
                'ext'  => 'htm',
                'type' => 'text/html',
            ],
        ], $model->attachment->variantsAttribute());

        static::assertEquals('htm', $model->attachment->variantExtension('test'));
    }

}
