<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Secretary;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // Global clinic search
    public function searchClinics(Request $request)
    {
        $query = Clinic::query();

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }



        $results = $query

            ->withCount('doctors')
            ->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No clinics found matching your search criteria'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $results->map(
                function ($clinic) {
                    return [
                        'id' => $clinic->id,
                        'name' => $clinic->name,
                        'image_path' => $clinic->getIconUrl(),
                        'doctors_count' => count($clinic->doctors)
/* $results = $query
        ->withCount('doctors')
        ->get();

 'doctors_count' => $clinic->doctors_count // Use the withCount result
 */


                    ];
                }

            )
        ]);
    }

    // Global doctor search
    public function searchDoctors(Request $request)
    {
        $query = Doctor::with(['user', 'clinic', 'reviews']);

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->whereHas('user', function ($uq) use ($keyword) {
                    $uq->where('first_name', 'like', "%$keyword%")
                        ->orWhere('last_name', 'like', "%$keyword%");
                })
                    ->orWhere('specialty', 'like', "%$keyword%")
                    ->orWhereHas('clinic', function ($cq) use ($keyword) {
                        $cq->where('name', 'like', "%$keyword%");
                    });
            });
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid keyword to search.'
            ], 400);
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No doctors found matching your search keyword'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $results->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'first_name' => $doctor->user->first_name,
                    'last_name' => $doctor->user->last_name,
                    'specialty' => $doctor->specialty,
                    'experience_years' => $doctor->experience_years,
                    'rate' => $doctor->rating,
                    'profile_picture_url' => $doctor->user->getFileUrl('profile_picture')
                ];
            })
        ],200);
    }



public function searchPatients(Request $request)
{
      $query = Patient::with(['user', 'appointments' => function($q) {
        $q->orderBy('appointment_date', 'desc')->limit(1);
    }]);

    if ($request->filled('keyword')) {
        $keyword = $request->keyword;

        $query->where(function ($q) use ($keyword) {
            $q->whereHas('user', function ($uq) use ($keyword) {
                $uq->where('first_name', 'like', "%$keyword%")
                   ->orWhere('last_name', 'like', "%$keyword%");
            })
            ->orWhere('phone_number', 'like', "%$keyword%")
            ->orWhereHas('user', function ($uq) use ($keyword) {
                $uq->where('email', 'like', "%$keyword%");
            });
        });
    } else {
        return response()->json([
            'success' => false,
            'message' => 'Please provide a valid keyword to search.'
        ], 400);
    }

    $results = $query->get();

    if ($results->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No patients found matching your search keyword'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $results->map(function ($patient) {
            $lastVisit = $patient->appointments->first();

            return [
                'patient_id' => $patient->id,
                'user_id' => $patient->user->id ,
                'patient_name' => $patient->user->first_name . ' '. $patient->user->last_name, // FIXED: Added ->user->
                'phone' => $patient->phone_number,
                'email' => $patient->user->email,
                'profile_picture_url' => $patient->user->getProfilePictureUrl(),
                'last_visit_at' => $lastVisit ? $lastVisit->appointment_date->format('Y-m-d') : 'Never'
            ];
        })
    ], 200);
}

    // Global secretary search
    public function searchSecretaries(Request $request)
    {
        $query = Secretary::with('user');

        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->name . '%')
                    ->orWhere('last_name', 'like', '%' . $request->name . '%');
            });
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No secretaries found matching your search criteria'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $results->map(function ($secretary) {
                return [
                    'id' => $secretary->id,
                    'name' => $secretary->user->full_name,
                    'email' => $secretary->user->email,
                    'workdays' => $secretary->workdays,
                    'profile_picture' => $secretary->user->getProfilePictureUrl()
                ];
            })
        ]);
    }
}
