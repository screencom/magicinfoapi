<?php

namespace Screencom\MagicinfoApi\Post;

class Api extends Magicinfo
{
    /** @var string */
    protected $loginendpoint;

    /** @var string */
    protected $csrfendpoint;

    /** @var array */
    protected $guzzle_options = [
        'timeout' => 10
    ];

    /**
     * Api constructor.
     *
     * @param string $endpoint
     * @param string $username
     * @param string $password
     * @param array $options
     *
     * @throws \Exception
     */
    public function __construct($endpoint, $username, $password, $options = [])
    {
        parent::__construct($endpoint, $username, $password, false);

        $this->loginendpoint = join('/', [$this->endpoint, 'j_spring_security_check']);
        $this->csrfendpoint = join('/', [$this->endpoint, 'login.htm?cmd=INIT']);

        if (! empty($options)) {
            $this->guzzle_options = $options;
        }

        $this->login();
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function csrf()
    {
        try {
            $response = $this->client->get($this->csrfendpoint, $this->guzzle_options);

            $body = $response->getBody()->getContents();

            if (preg_match('/<input type="hidden" value="(.*?)" name="_csrf">/', $body, $match)) {
                return $match[1];
            }

            throw new \Exception('Unable to retrieve csrf token @ ' . $this->csrfendpoint);
        } catch (\Exception $e) {
            throw new \Exception('Unable to retrieve csrf token @ ' . $this->csrfendpoint);
        }

    }

    /**
     * @throws \Exception
     */
    public function login()
    {
        $response = $this->client->post($this->loginendpoint, [
            'form_params'    => [
                'j_username' => $this->username,
                'j_password' => $this->password,
                '_csrf'      => $this->csrf()
            ],
            'headers'        => [
                'Accept'  => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Referer' => $this->endpoint . '/login.htm?cmd=INIT'
            ],
            //'connect_timeout' => 5,
            //'debug' => true
        ]);

        // $body = $response->getBody()->getContents();
    }

    /**
     * @param $zipfile
     * @param $contentId
     *
     * @return stdClass
     */
    public function uploadWebPackage($zipfile, $contentId)
    {
        return $this->uploadWebContent($zipfile, $contentId);
    }

    /**
     * @param $filename
     * @param $contentId
     * @return mixed
     */
    public function uploadWebContent($filename, $contentId)
    {
        $response = $this->client->get($this->endpoint . '/content/getContentView.htm?cmd=VIEW&contentId=' . $contentId . '&_=' . (time() * 1000));

        $body = $response->getBody()->getContents($filename, $contentId);

        $menu = \GuzzleHttp\json_decode($body)->result->menu;

        $query = http_build_query([
            'groupId'         => '',
            'contentType'     => $menu->mediaType,
            'webContentName'  => $menu->contentName,
            'startPage'       => $menu->html_start_page,
            'mode'            => 'update',
            'contentId'       => $contentId,
            'refreshInterval' => $menu->refresh_interval,
        ]);

        $response = $this->client->post($this->endpoint . '/servlet/ContentFileUpload?' . $query, [
            'multipart' => [
                [
                    'name'     => basename($filename),
                    'contents' => fopen($filename, 'r')
                ]
            ],
            //'connect_timeout' => 5,
            //'debug' => true
        ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    /**
     * @param $filename
     * @param string|null $contentId
     * @return mixed
     */
    public function uploadImageContent($filename, $contentId = null)
    {
        if (is_null($contentId)) {
            /**
             * New content item
             */
            $query = http_build_query([
                'groupId' => '',
            ]);

            $options = [
                'multipart' => [
                    [
                        'name'     => 'categoryId',
                        'contents' => '',
                    ],
                    [
                        'name'     => 'files[]',
                        'contents' => fopen($filename, 'r'),
                    ],
                ],
            ];
        } else {
            /**
             * Existing content item
             */
            $query = http_build_query([
                'contentId' => $contentId,
            ]);

            $options = [
                'multipart' => [
                    [
                        'name'     => 'category',
                        'contents' => 1,
                    ],
                    [
                        'name'     => 'file',
                        'contents' => fopen($filename, 'r'),
                    ],
                ],
            ];
        }

        $response = $this->client->post($this->endpoint . '/servlet/ContentFileUpload?' . $query, $options);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    /**
     * @param $filename
     * @param string|null $contentId
     *
     * @param string $type
     * @return mixed
     */
    public function uploadContent($filename, $contentId = null, $type = 'web')
    {
        if ($type == 'web') {
            return $this->uploadWebContent($filename, $contentId);
        } else {
            return $this->uploadImageContent($filename, $contentId);
        }
    }

    /**
     * @param $data
     *
     * user_id: misadmin3
     * new_organization: MIS3
     * group_name: default
     * password: misadmin3misadmin3
     * user_name: misadmin3
     * email: misadmin3@screencom.eu
     * role_name: Administrator
     *
     * @return mixed
     */
    public function createUser($data)
    {
        /**
         * &user_id=misadmin3&new_organization=MIS3&group_name=default&password=misadmin3misadmin3
         * &user_name=misadmin3&email=misadmin3%40screencom.eu&mobile_num=&phone_num=&role_name=Administrator&team_name=&job_position=&_=1569588804903
         */

        $user_query = http_build_query($data);

        $url = $this->endpoint . '/user/getUser.htm?cmd=saveOrg&' . $user_query;

        $response = $this->client->get($url);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }
}
