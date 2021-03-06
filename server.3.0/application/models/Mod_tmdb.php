<?php

class Mod_tmdb extends CI_Model {

    function __construct() {

        parent::__construct();
        $this->load->library('tmdb');
    }

    //--------------------------------------------------------------------------
    //  B O X   O F F I C E
    //--------------------------------------------------------------------------

    function BoxOffice($return = false, $check = false ) {

        if ($check === true) {
            $this->CheckBoxOffice();
        }

        $sql = "
            select *
            from tmdb_boxoffice
            where uid = (select max(uid) from tmdb_boxoffice)
        ";

        do {
            $query = $this->db->query($sql);
            $rows = $query->num_rows();
        } while ($rows == 0);

        $popular = $boxoffice = $popular_tv = $boxoffice_tv = $records = array();

        foreach ($query->result() as $row) {

            if ($row->type === 'B' || $row->type === 'P') {
                $type = 'M';
            } else {
                $type = 'T';
            }

			$record = array(
                    'id' => $row->id,
                    'type' => $type,
                    'name' => $row->name,
                    'thumb' => $row->img,
                    'popularity' => $row->popularity,
                    'vote_average' => $row->vote_average,
                    'release_date' => $row->release_date,
                    'date' => date('Ymd', strtotime($row->uid))
                );

            if ($row->type == 'B') {
                $boxoffice[] = $record;
            } else if ($row->type == 'P') {
                $popular[] = $record;
            } else if ($row->type == 'T') {
                $boxoffice_tv[] = $record;
            }  else if ($row->type == 'R') {
                $popular_tv[] = $record;
            }

        }

        $records['boxoffice'] = count($boxoffice) > 0 ? $boxoffice : array();
        $records['popular'] = count($popular) > 0 ? $popular : array();
        $records['boxoffice_tv'] = count($boxoffice_tv) > 0 ? $boxoffice_tv : array();
        $records['popular_tv'] = count($popular_tv) > 0 ? $popular_tv : array();


        $fb = array(
            'success' => true,
            'records' => $records,
            'total' => $rows,
            'message' => 'OK'
        );

        if ($return == true)
            return $fb;

        return json_encode($fb);
    }

    function CheckBoxOffice() {

        $sql = "
            select *, uid as maxuid from tmdb_boxoffice
            order by uid desc
        ";

        $query = $this->db->query($sql);
        if ($query->num_rows() == 0) {
            $this->tmdb();
            return;
        }

        foreach ($query->result() as $row) {
            $today = date('Ymd', time());
            $maxdate = date('Ymd', strtotime($row->maxuid));

            if ($today != $maxdate) {
                $this->tmdb();
            }
            return;
        }
    }

    private function tmdb() {

        $this->startTime = microtime(true);
        $this->load->model('runtime_log');
        $this->CrawlPopular();
    }

    //--------------------------------------------------------------------------
    // "C R A W L" the popular movies on TMDB
    //--------------------------------------------------------------------------

    private function CrawlPopular() {

        $configuration = $this->tmdb->getConfig();
        if (!is_array($configuration))
            return false;

        $path = $configuration['images']['base_url'] . 'w92';
        $no_image = '/images/tmdb/no-poster-w92.jpg';

        $uid = time();

        $boxoffice = json_decode($this->tmdb->MovieNowPlaying());

        foreach ($boxoffice->results as $row) {

            if ($row != null) {

                $this->AddBoxoffice(array(
                    'type' => 'B',
                    'uid' => date('Y-m-d H:i:s', $uid),
                    'id' => $row->id,
                    'name' => $row->original_title,
                    'popularity' => $row->popularity,
                    'vote_average' => $row->vote_average,
                    'release_date' => $row->release_date,
                    'img' => $row->poster_path != '' ? $path . $row->poster_path : $no_image,
                    'c_date' => date('Y.m.d'),
                    'c_time' => date('H:i:s')
                ));
            }
        }

        $popular = json_decode($this->tmdb->MoviePopular());

        foreach ($popular->results as $row) {

            if ($row != null) {

                $this->AddBoxoffice(array(
                    'type' => 'P',
                    'uid' => date('Y-m-d H:i:s', $uid),
                    'id' => $row->id,
                    'name' => $row->original_title,
                    'popularity' => $row->popularity,
                    'vote_average' => $row->vote_average,
                    'release_date' => $row->release_date,
                    'img' => $row->poster_path != '' ? $path . $row->poster_path : $no_image,
                    'c_date' => date('Y.m.d'),
                    'c_time' => date('H:i:s')
                ));
            }
        }

		$popular_tv = json_decode($this->tmdb->TvPopular());

        foreach ($popular_tv->results as $row) {

            if ($row != null) {

                $this->AddBoxoffice(array(
                    'type' => 'T',
                    'uid' => date('Y-m-d H:i:s', $uid),
                    'id' => $row->id,
                    'name' => $row->original_name,
                    'img' => $row->poster_path != '' ? $path . $row->poster_path : $no_image,
                    'popularity' => $row->popularity,
                    'vote_average' => $row->vote_average,
                    'release_date' => $row->first_air_date,
                    'c_date' => date('Y.m.d'),
                    'c_time' => date('H:i:s')
                ));
            }
        }

		$toprated_tv = json_decode($this->tmdb->TvTopRated());

        foreach ($toprated_tv->results as $row) {

            if ($row != null) {

                $this->AddBoxoffice(array(
                    'type' => 'R',
                    'uid' => date('Y-m-d H:i:s', $uid),
                    'id' => $row->id,
                    'name' => $row->original_name,
                    'popularity' => $row->popularity,
                    'vote_average' => $row->vote_average,
                    'release_date' => $row->first_air_date,
                    'img' => $row->poster_path != '' ? $path . $row->poster_path : $no_image,
                    'c_date' => date('Y.m.d'),
                    'c_time' => date('H:i:s')
                ));
            }
        }
    }

    function AddBoxoffice($info = array()) {

        $record = array(
            'id' => $info['id'],
            'type' => $info['type'],
            'name' => $info['name'],
            'img' => $info['img'],
            'popularity' => $info['popularity'],
            'release_date' => $info['release_date'],
            'vote_average' => $info['vote_average'],
            'uid' => $info['uid'],
            'c_date' => date('Y.m.d'),
            'c_time' => date('H:i:s')
        );

        $this->db->insert('tmdb_boxoffice', $record);

        return true;
    }

    //--------------------------------------------------------------------------
    //  P O P U L A R   O N   M O V I E W O R L D
    //--------------------------------------------------------------------------

    function MoviesPopular() {
        $result = $this->PopularQuery('M');
        return $result;
    }

    function PersonsPopular() {
        $result = $this->PopularQuery('P');
        return $result;
    }

    private function PopularQuery($type = 'P') {
        $sql = "
            select tmdb_items.id, tmdb_items.type, tmdb_items.name, tmdb_items.thumb as thumbnail,
                   tmdb_items.datum as item_date
            from tmdb_chart
            LEFT OUTER JOIN tmdb_items
                on (tmdb_chart.id = tmdb_items.id and tmdb_chart.type = tmdb_items.type)
            where tmdb_items.type = '$type'
            order by tmdb_chart.hits desc, tmdb_items.name
            limit 15
        ";

        $query = $this->db->query($sql);
        $rows = $query->num_rows();

        $records = array();
        foreach ($query->result() as $row) {
            $records[] = $row;
        }

        $fb = array(
            'success' => true,
            'records' => $records,
            'total' => $rows,
            'message' => 'OK'
        );

        return $fb;
    }

    //--------------------------------------------------------------------------
    //  C A C H I N G
    //--------------------------------------------------------------------------

    public function cache_write($id = false, $type = false, $fb = false, $page = 1) {

        if ($this->cache_read($id, $type, $page) != false)
            return false;

        $data = array(
            'id' => $id,
            'type' => $type,
            'page' => $page,
            'json' => $fb,
            'c_date' => date('Y.m.d H:i:s')
        );

        $this->db->insert('tmdb_cache', $data);
    }

    public function cache_read($id = false, $type = false, $page = false) {

        $this->db->select('*');
        $this->db->select_max('c_date', 'max_date');
        $this->db->where('id', $id);
        $this->db->where('type', $type);
        if ($page !== false) {
            $this->db->where('page', $page);
        }
        $query = $this->db->get('tmdb_cache');

        if ($query->num_rows == 0)
            return false;

        foreach ($query->result() as $row) {
            if (strtotime($row->max_date) < strtotime('-14 days')) {
                return false;
            }
            return $row->json;
        }
    }

    //--------------------------------------------------------------------------
    // RECORD THE HIT STATISTICS
    //--------------------------------------------------------------------------

    public function Hits($info = false) {

        if ($info == false)
            return;

        $id = array_key_exists('id', $info) ? $info['id'] : false;
        $type = array_key_exists('type', $info) ? $info['type'] : false;

        if ($id == false || $type == false)
            return false;

        $this->db->where('id', $id);
        $this->db->where('type', $type);
        $query = $this->db->get('tmdb_chart');

        if ($query->num_rows > 0) {
            $date = date('Y.m.d H:i:s');
            $sql = "
            update tmdb_chart
            set    hits = hits+1,
                   m_date = '$date'
            where  id = '$id'
            and    type = '$type'
            ";
            $this->db->query($sql);
        } else {
            $data = array(
                'id' => $id,
                'type' => $type,
                'hits' => 1,
                'c_date' => date('Y.m.d H:i:s')
            );
            $this->db->insert('tmdb_chart', $data);
        }

        $data = array(
            'id' => $id,
            'type' => $type,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'c_date' => date('Y.m.d H:i:s')
        );
        $this->db->insert('tmdb_hits', $data);

        // add to register of items
        $this->Items($info);
    }

    //--------------------------------------------------------------------------
    //  TMDB ITEM REGISTER, FOR FAST NAME AND THUMB REFERENCE
    //--------------------------------------------------------------------------

    private function Items($info = false) {

        if ($info == false)
            return;

        $id = array_key_exists('id', $info) ? $info['id'] : false;
        $type = array_key_exists('type', $info) ? $info['type'] : false;

        if ($id == false || $type == false)
            return false;

        $this->db->where('id', $id);
        $this->db->where('type', $type);
        $query = $this->db->get('tmdb_items');

        if ($query->num_rows() == 0) {
            $data = array(
                'id' => $id,
                'type' => $type,
                'thumb' => array_key_exists('thumb', $info) ? $info['thumb'] : '',
                'name' => array_key_exists('name', $info) ? $info['name'] : '',
                'datum' => array_key_exists('datum', $info) ? $info['datum'] : '0000:00:00',
                'c_date' => date('Y.m.d H:i:s')
            );
            $this->db->insert('tmdb_items', $data);
        }
    }

    //--------------------------------------------------------------------------
    // ADD SEARCH ARGUMENT
    //--------------------------------------------------------------------------

    function AddSearch($search = false) {
        $data = array(
            'search' => $search,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'country' => '',
            'c_date' => date('Y.m.d H:i:s')
        );
        $this->db->insert('tmdb_search', $data);
    }

}
