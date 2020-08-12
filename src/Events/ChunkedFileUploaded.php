<?php

namespace Cybex\Lodor\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChunkedFileUploaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * UUID of the file that just finished uploading.
     *
     * @var string
     */
    public $uuid;

    /**
     * Metadata of the file upload (form data etc.).
     *
     * @var array
     */
    public $metadata;

    /**
     * Create a new event instance.
     *
     * @param string $uuid
     * @param array  $metadata
     */
    public function __construct(string $uuid, array $metadata)
    {
        $this->uuid     = $uuid;
        $this->metadata = $metadata;
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
