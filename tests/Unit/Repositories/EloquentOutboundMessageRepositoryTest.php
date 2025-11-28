<?php

/**
 * Unit tests for EloquentOutboundMessageRepository.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Tests\Unit\Repositories
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Tests\Unit\Repositories;

use Equidna\BirdFlock\Models\OutboundMessage;
use Equidna\BirdFlock\Repositories\EloquentOutboundMessageRepository;
use Equidna\BirdFlock\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class EloquentOutboundMessageRepositoryTest extends TestCase
{
    public function testUpdateStatusSetsSentAtWhenStatusIsSent(): void
    {
        $mockModel = $this->createMock(OutboundMessage::class);
        $mockModel->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($data) {
                return $data['status'] === 'sent'
                    && isset($data['sentAt'])
                    && isset($data['providerMessageId']);
            }));

        $mockBuilder = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockBuilder->expects($this->once())
            ->method('orWhere')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('lockForUpdate')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('first')
            ->willReturn($mockModel);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        OutboundMessage::partialMock()
            ->shouldReceive('where')
            ->once()
            ->with('providerMessageId', 'MSG123')
            ->andReturn($mockBuilder);

        $repository = new EloquentOutboundMessageRepository();
        $repository->updateStatus('MSG123', 'sent', ['provider_message_id' => 'PROV123']);
    }

    public function testUpdateStatusSetsDeliveredAtWhenStatusIsDelivered(): void
    {
        $mockModel = $this->createMock(OutboundMessage::class);
        $mockModel->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($data) {
                return $data['status'] === 'delivered'
                    && isset($data['deliveredAt']);
            }));

        $mockBuilder = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockBuilder->expects($this->once())
            ->method('orWhere')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('lockForUpdate')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('first')
            ->willReturn($mockModel);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        OutboundMessage::partialMock()
            ->shouldReceive('where')
            ->once()
            ->with('providerMessageId', 'MSG123')
            ->andReturn($mockBuilder);

        $repository = new EloquentOutboundMessageRepository();
        $repository->updateStatus('MSG123', 'delivered');
    }

    public function testUpdateStatusSetsFailedAtWhenStatusIsFailed(): void
    {
        $mockModel = $this->createMock(OutboundMessage::class);
        $mockModel->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($data) {
                return $data['status'] === 'failed'
                    && isset($data['failedAt'])
                    && isset($data['errorCode'])
                    && isset($data['errorMessage']);
            }));

        $mockBuilder = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockBuilder->expects($this->once())
            ->method('orWhere')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('lockForUpdate')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('first')
            ->willReturn($mockModel);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        OutboundMessage::partialMock()
            ->shouldReceive('where')
            ->once()
            ->with('providerMessageId', 'MSG123')
            ->andReturn($mockBuilder);

        $repository = new EloquentOutboundMessageRepository();
        $repository->updateStatus('MSG123', 'failed', [
            'error_code' => 'ERR',
            'error_message' => 'Failed'
        ]);
    }

    public function testUpdateStatusDoesNothingWhenMessageNotFound(): void
    {
        $mockBuilder = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockBuilder->expects($this->once())
            ->method('orWhere')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('lockForUpdate')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('first')
            ->willReturn(null);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        OutboundMessage::partialMock()
            ->shouldReceive('where')
            ->once()
            ->with('providerMessageId', 'NONEXISTENT')
            ->andReturn($mockBuilder);

        $repository = new EloquentOutboundMessageRepository();
        $repository->updateStatus('NONEXISTENT', 'sent');

        $this->assertTrue(true);
    }

    public function testFindByIdempotencyKeyReturnsArrayWhenFound(): void
    {
        $mockModel = $this->createMock(OutboundMessage::class);
        $mockModel->expects($this->once())
            ->method('toArray')
            ->willReturn(['id' => 'MSG123', 'status' => 'sent']);

        $mockBuilder = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockBuilder->expects($this->once())
            ->method('first')
            ->willReturn($mockModel);

        OutboundMessage::partialMock()
            ->shouldReceive('where')
            ->once()
            ->with('idempotencyKey', 'IDEMPOTENCY123')
            ->andReturn($mockBuilder);

        $repository = new EloquentOutboundMessageRepository();
        $result = $repository->findByIdempotencyKey('IDEMPOTENCY123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testFindByIdempotencyKeyReturnsNullWhenNotFound(): void
    {
        $mockBuilder = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockBuilder->expects($this->once())
            ->method('first')
            ->willReturn(null);

        OutboundMessage::partialMock()
            ->shouldReceive('where')
            ->once()
            ->with('idempotencyKey', 'NONEXISTENT')
            ->andReturn($mockBuilder);

        $repository = new EloquentOutboundMessageRepository();
        $result = $repository->findByIdempotencyKey('NONEXISTENT');

        $this->assertNull($result);
    }

    public function testResetForRetryResetsAllFields(): void
    {
        $mockModel = $this->createMock(OutboundMessage::class);
        $mockModel->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($data) {
                return $data['status'] === 'queued'
                    && isset($data['queuedAt'])
                    && $data['sentAt'] === null
                    && $data['deliveredAt'] === null
                    && $data['failedAt'] === null
                    && $data['providerMessageId'] === null
                    && $data['errorCode'] === null
                    && $data['errorMessage'] === null
                    && $data['attempts'] === 0
                    && $data['idempotencyKey'] === 'NEW_KEY';
            }));

        $mockBuilder = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockBuilder->expects($this->once())
            ->method('lockForUpdate')
            ->willReturnSelf();

        $mockBuilder->expects($this->once())
            ->method('first')
            ->willReturn($mockModel);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        OutboundMessage::partialMock()
            ->shouldReceive('where')
            ->once()
            ->with('id_outboundMessage', 'MSG123')
            ->andReturn($mockBuilder);

        $repository = new EloquentOutboundMessageRepository();
        $repository->resetForRetry('MSG123', ['idempotencyKey' => 'NEW_KEY']);
    }
}
