<?php

namespace Cybex\Lodor\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * UUID of the file that just failed uploading.
     *
     * @var string
     */
    public $uuid;

    /**
     * The reason for the failure.
     *
     * @var string
     */
    public $errorMessage;

    /**
     * Optional metadata / context of the error.
     *
     * @var array
     */
    public $metadata;

    /**
     * Create a new event instance.
     *
     * @param string $uuid
     * @param string $errorMessage
     * @param array  $metadata
     */
    public function __construct(string $uuid, string $errorMessage = '', array $metadata = [])
    {
        $this->uuid         = $uuid;
        $this->errorMessage = $errorMessage;
        $this->metadata     = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel(config('lodor.events.channel'));
    }
}
