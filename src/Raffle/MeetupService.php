<?php

namespace Raffle;

use DMS\Service\Meetup\AbstractMeetupClient;

class MeetupService
{
    /**
     * Meetup client
     *
     * @var AbstractMeetupClient
     */
    protected $client;

    /**
     * Meetup group
     *
     * @var string
     */
    protected $group;

    /**
     * Constructor. Sets dependencies.
     *
     * @param AbstractMeetupClient $client
     * @param string $group
     *
     * @return \Raffle\MeetupService
     */
    public function __construct(AbstractMeetupClient $client, $group)
    {
        $this->client = $client;
        $this->group      = $group;
    }

    /**
     * Fetch all events in the past and up to a day in the future.
     *
     * @return array
     */
    public function getEvents()
    {
        // Fetch past and future events (only upcoming contains the current event)
        $events = $this->client->getEvents(
            array(
                'group_urlname' => $this->group,
                'status' => 'past,upcoming',
                'desc' => 'desc'
            )
        );

        // Filter out events further in the future than a day
        $dayFromNow = (time() + (24 * 60 * 60)) * 1000;
        $events = $events->filter(function($value) use ($dayFromNow) {
                return ($value['time'] < $dayFromNow)? true : false;
        });

        return $events;
    }

    /**
     * Get a single event.
     *
     * @param string $id
     * @return array
     */
    public function getEvent($id)
    {
        // Fetch, event, checkins and RSVPs (only the latter has pictures)
        $event    = $this->client->getEvent(array('id' => $id));
        $checkins = $this->client->getCheckins(array('event_id' => $id));
        $rsvps    = $this->client->getRSVPs(array('event_id' => $id));

        // Intersect the RSVPs with the checkins and add them to the event array
        $checkedInMemberIds = array();
        foreach ($checkins as $checkin) {
            $checkedInMemberIds[] = $checkin['member_id'];
        }

        $event = $event->toArray();
        $event['checkins'] = array();
        foreach ($rsvps as $rsvp) {
            if (in_array($rsvp['member']['member_id'], $checkedInMemberIds)) {
                $event['checkins'][] = array(
                    'id' => $rsvp['member']['member_id'],
                    'name' => $rsvp['member']['name'],
                    'photo' => $rsvp['member_photo']
                );
            }
        }

        return $event;
    }
}
