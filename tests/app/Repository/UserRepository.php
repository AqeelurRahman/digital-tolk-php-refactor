<?php

namespace DTApi\Repository;

use DTApi\Models\Company;
use DTApi\Models\Department;
use DTApi\Models\Type;
use DTApi\Models\UsersBlacklist;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use DTApi\Models\User;
use DTApi\Models\Town;
use DTApi\Models\UserMeta;
use DTApi\Models\UserTowns;
use DTApi\Events\JobWasCreated;
use DTApi\Models\UserLanguages;
use Monolog\Handler\StreamHandler;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\FirePHPHandler;

/**
 * Class BookingRepository
 * @package DTApi\Repository
 */
class UserRepository extends BaseRepository
{

    protected $model;
    protected $logger;

    /**
     * @param User $model
     */
    function __construct(User $model)
    {
        parent::__construct($model);
//        $this->mailer = $mailer;
        $this->logger = new Logger('admin_logger');

        $this->logger->pushHandler(new StreamHandler(
            storage_path('logs/admin/laravel-' . date('Y-m-d') . '.log'),
            Logger::DEBUG));
        $this->logger->pushHandler(new FirePHPHandler());
    }


    public function enable($user, $id)
    {
        $user->status = '1';
        $user->save();

    }

    public function disable($user, $id)
    {
        $user->status = '0';
        $user->save();
    }

    public function getTranslators()
    {
        return User::where('user_type', 2)->get();
    }

    public function CreateOrUpdate($request, $id = null)
    {
        $user = $this->getUser($id);

        $user = $this->updateUserDetails($user, $request);

        if (!$id || $id && $request['password']) {
            $user = $this->updateUserPassword($user, $request['password']);
        }

        if ($user->hasRole(env('CUSTOMER_ROLE_ID'))) {
            $this->updateCustomerDetails($user, $request);
            $this->updateUserBlacklist($user, $request);
        } elseif ($user->hasRole(env('TRANSLATOR_ROLE_ID'))) {
            $this->updateTranslatorDetails($user, $request);
        }
        if ($request['new_towns']) {
            $this->saveTowns($request, $user);
        }
        $this->changeUserStatus($request, $user);

        return $user;
    }

    private function getUser(mixed $id)
    {
        return is_null($id) ? new User : User::findOrFail($id);
    }

    private function updateUserDetails(User $user, $request)
    {
        $user->user_type = $request['role'];
        $user->name = $request['name'];
        $user->company_id = $request['company_id'] ?: 0;
        $user->department_id = $request['department_id'] ?: 0;
        $user->email = $request['email'];
        $user->dob_or_orgid = $request['dob_or_orgid'];
        $user->phone = $request['phone'];
        $user->mobile = $request['mobile'];
        $user->detachAllRoles();
        $user->attachRole($request['role']);
        $user->save();

        return $user;
    }

    private function updateUserPassword(User $user, $password)
    {
        if ($password) {
            $user->password = bcrypt($password);
            $user->save();
        }

        return $user;
    }

    private function updateCustomerDetails($user, $request)
    {
        if ($request['consumer_type'] == 'paid' && $request['company_id'] == '') {
            $type = Type::where('code', 'paid')->first();
            $company = Company::create(['name' => $request['name'], 'type_id' => $type->id,
                'additional_info' => 'Created automatically for user ' . $user->id]);
            $department = Department::create(['name' => $request['name'], 'company_id' => $company->id,
                'additional_info' => 'Created automatically for user ' . $user->id]);

            $user->company_id = $company->id;
            $user->department_id = $department->id;
            $user->save();
        }
        $userMeta = UserMeta::firstOrCreate(['user_id' => $user->id]);
        $userMeta->consumer_type = $request['consumer_type'];
        $userMeta->customer_type = $request['customer_type'];
        $userMeta->username = $request['username'];
        $userMeta->post_code = $request['post_code'];
        $userMeta->address = $request['address'];
        $userMeta->city = $request['city'];
        $userMeta->town = $request['town'];
        $userMeta->country = $request['country'];
        $userMeta->reference = isset($request['reference']) && $request['reference'] === 'yes' ? '1' : '0';
        $userMeta->additional_info = $request['additional_info'];
        $userMeta->cost_place = $request['cost_place'] ?? '';
        $userMeta->fee = $request['fee'] ?? '';
        $userMeta->time_to_charge = $request['time_to_charge'] ?? '';
        $userMeta->time_to_pay = $request['time_to_pay'] ?? '';
        $userMeta->charge_ob = $request['charge_ob'] ?? '';
        $userMeta->customer_id = $request['customer_id'] ?? '';
        $userMeta->charge_km = $request['charge_km'] ?? '';
        $userMeta->maximum_km = $request['maximum_km'] ?? '';
        $userMeta->save();
    }

    private function updateUserBlacklist($user, $request)
    {
        $id = $user->id;
        $blacklistUpdated = [];
        $userBlacklist = UsersBlacklist::where('user_id', $id)->get();
        $userTranslId = collect($userBlacklist)->pluck('translator_id')->all();

        $diff = null;
        if ($request['translator_ex']) {
            $diff = array_intersect($userTranslId, $request['translator_ex']);
        }
        if ($diff || $request['translator_ex']) {
            foreach ($request['translator_ex'] as $translatorId) {
                $blacklist = new UsersBlacklist();
                if ($user->id) {
                    $already_exist = UsersBlacklist::translatorExist($user->id, $translatorId);
                    if ($already_exist == 0) {
                        $blacklist->user_id = $user->id;
                        $blacklist->translator_id = $translatorId;
                        $blacklist->save();
                    }
                    $blacklistUpdated [] = $translatorId;
                }
            }
            if ($blacklistUpdated) {
                UsersBlacklist::deleteFromBlacklist($user->id, $blacklistUpdated);
            }
        } else {
            UsersBlacklist::where('user_id', $user->id)->delete();
        }
    }

    private function updateTranslatorDetails($user, $request)
    {
        $data = [];
        $userMeta = UserMeta::firstOrCreate(['user_id' => $user->id]);

        $userMeta->translator_type = $request['translator_type'];
        $userMeta->worked_for = $request['worked_for'];
        if ($request['worked_for'] == 'yes') {
            $userMeta->organization_number = $request['organization_number'];
        }
        $userMeta->gender = $request['gender'];
        $userMeta->translator_level = $request['translator_level'];
        $userMeta->additional_info = $request['additional_info'];
        $userMeta->post_code = $request['post_code'];
        $userMeta->address = $request['address'];
        $userMeta->address_2 = $request['address_2'];
        $userMeta->town = $request['town'];
        $userMeta->save();

        $data['translator_type'] = $request['translator_type'];
        $data['worked_for'] = $request['worked_for'];
        if ($request['worked_for'] == 'yes') {
            $data['organization_number'] = $request['organization_number'];
        }
        $data['gender'] = $request['gender'];
        $data['translator_level'] = $request['translator_level'];

        if ($request['user_language']) {
            $this->updateUserLanguages($request, $user);
        }
    }

    private function saveTowns($request, User $user)
    {
        $towns = new Town;
        $towns->townname = $request['new_towns'];
        $towns->save();

        $townIdUpdated = [];
        if ($request['user_towns_projects']) {
            $del = DB::table('user_towns')->where('user_id', '=', $user->id)->delete();
            foreach ($request['user_towns_projects'] as $townId) {
                $userTown = new UserTowns();
                $alreadyExists = $userTown::townExist($user->id, $townId);
                if ($alreadyExists == 0) {
                    $userTown->user_id = $user->id;
                    $userTown->town_id = $townId;
                    $userTown->save();
                }
                $townIdUpdated[] = $townId;
            }
        }
    }

    private function changeUserStatus($request, User $user)
    {
        if ($request['status'] == '1') {
            if ($user->status != '1') {
                $this->enable($user, $user->id);
            }
        } else {
            if ($user->status != '0') {
                $this->disable($user, $user->id);
            }
        }
    }

    private function updateUserLanguages($request, $user)
    {
        foreach ($request['user_language'] as $langId) {
            $userLang = new UserLanguages();
            $already_exit = $userLang::langExist($user->id, $langId);
            if ($already_exit == 0) {
                $userLang->user_id = $user->id;
                $userLang->lang_id = $langId;
                $userLang->save();
            }
            $langIdUpdated[] = $langId;

        }
        if ($langIdUpdated) {
            $userLang::deleteLang($user->id, $langIdUpdated);
        }
    }

}
