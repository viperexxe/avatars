<?php

namespace lemage\avatars\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    /** @var \phpbb\avatar\manager */
    protected $avatar_manager;

    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\request\request */
    protected $request;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var string */
    protected $root_path;

    /** @var string */
    protected $php_ext;

    /** @var array Cache for user avatar data to reduce queries */
    protected $user_cache = [];

    public function __construct(
        \phpbb\avatar\manager $avatar_manager,
        \phpbb\user $user,
        \phpbb\request\request $request,
        \phpbb\db\driver\driver_interface $db,
        $root_path,
        $php_ext
    ) {
        $this->avatar_manager = $avatar_manager;
        $this->user = $user;
        $this->request = $request;
        $this->db = $db;
        $this->root_path = $root_path;
        $this->php_ext = $php_ext;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.display_forums_modify_template_vars' => 'add_last_post_avatars_index',
            'core.viewforum_modify_topicrow'           => 'add_last_post_avatars_viewforum',
        ];
    }



    /**
     * Add avatars to forum list (index)
     */
    public function add_last_post_avatars_index($event)
    {
        $forum_row = $event['forum_row'];
        $db_row = $event['row'];
        
        // Use forum_last_poster_id from DB row
        $last_poster_id = isset($db_row['forum_last_poster_id']) ? (int) $db_row['forum_last_poster_id'] : 0;

        if ($last_poster_id > ANONYMOUS) {
            $avatar_html = $this->get_user_avatar($last_poster_id);
            if ($avatar_html) {
                $forum_row['LEMAGE_LAST_POST_AVATAR'] = $avatar_html;
                $event['forum_row'] = $forum_row;
            }
        }
    }

    /**
     * Add avatars to topic list (viewforum)
     */
    public function add_last_post_avatars_viewforum($event)
    {
        $topic_row = $event['topic_row'];
        $db_row = $event['row'];
        
        $last_poster_id = isset($db_row['topic_last_poster_id']) ? (int) $db_row['topic_last_poster_id'] : 0;

        if ($last_poster_id > ANONYMOUS) {
            $avatar_html = $this->get_user_avatar($last_poster_id);
            if ($avatar_html) {
                $topic_row['LEMAGE_LAST_POST_AVATAR'] = $avatar_html;
                $event['topic_row'] = $topic_row;
            }
        }
    }


    /**
     * Helper to get user avatar HTML
     */
    protected function get_user_avatar($user_id)
    {
        if (isset($this->user_cache[$user_id])) {
            return $this->user_cache[$user_id];
        }

        // We need user_email for Gravatar support
        $sql = 'SELECT user_avatar, user_avatar_type, user_avatar_width, user_avatar_height, user_email
                FROM ' . USERS_TABLE . '
                WHERE user_id = ' . (int) $user_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($row && ($row['user_avatar'] || $row['user_avatar_type'] == 'avatar.driver.gravatar')) {
            // Use phpBB_get_user_avatar which handles the 'user_' prefix cleaning
            $avatar_html = phpbb_get_user_avatar($row, 'Avatar', true);
            if ($avatar_html) {
                $this->user_cache[$user_id] = '<div class="lemage-last-post-avatar">' . $avatar_html . '</div>';
                return $this->user_cache[$user_id];
            }
        }

        $this->user_cache[$user_id] = '';
        return '';
    }


}
