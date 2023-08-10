<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user_type = $request->__authenticatedUser->user_type;
        // I think it is more secure if we fetch these role ids from configs instead of direct .env file
        $admin_roles = array(
            env('ADMIN_ROLE_ID'),
            env('SUPERADMIN_ROLE_ID'),
        );

        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobs($user_id);

        }
        elseif(in_array($user_type, $admin_roles))
        {
            $response = $this->repository->getAll($request);
        }

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        return response($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->store($user, $data);

        return response($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->except(['_token', 'submit']);
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, $data, $cuser);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $distance = $data['distance'] ?? "";
        $time     = $data['time'] ?? "";
        $jobid    = $data['jobid'] ?? "";
        $session  = $data['session_time'] ?? "";

        if ($data['flagged'] == 'true') {
            if(empty($data['admincomment'])) return "Please, add comment";
            $flagged = 'yes';
        } else {
            $flagged = 'no';
        }

        $manually_handled = ($data['manually_handled'] == 'true') ? 'yes' : 'no';
        $by_admin = ($data['by_admin'] == 'true') ? 'yes' : 'no';

        $admincomment = $data['admincomment'] ?? "";

        if ($time || $distance) {

            Distance::where('job_id', '=', $jobid)->update(array(
                'distance' => $distance, 
                'time' => $time
            ));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {

            Job::where('id', '=', $jobid)->update(array(
                'admin_comments' => $admincomment, 
                'flagged' => $flagged, 
                'session_time' => $session, 
                'manually_handled' => $manually_handled, 
                'by_admin' => $by_admin
            ));
        }

        return response('Record updated!');
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()]);
        }
    }

}
