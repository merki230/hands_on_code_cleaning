<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\CompetitionInstance;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\CustomFieldTeamEvent;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $events = Event::with(['arbitration_area','division','stage','stade','teams','competition_instance', 'eventSuspensions.sportsman'])->get();
        return response()->json($events , 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $result = Event::create($this->validator());
        if(request()->has('teams')){
            $result->teams()->attach(request('teams'));
        }
        $this->storeImage($result);
        return response()->json($result, 200);
    }
    public function storeMultiple(){
        request()->validate([
          'events'                                  => 'required|Array',
          'events.*.arbitration_area_id'            => 'nullable|integer|exists:arbitration_areas,id',
          'events.*.auto_referee'                   => 'nullable|boolean',
          'events.*.competition_instance_id'        => 'required|integer|exists:competition_instances,id',
          'events.*.description'                    => 'nullable|string',
          'events.*.description'                    => 'nullable|string',
          'events.*.location'                       => 'nullable|string',
          'events.*.seniority_referee'              => 'nullable|integer',
          'events.*.stade_id'                       => 'nullable|integer|exists:stades,id',
          'events.*.start_date'                     => 'required|date'
        ]) ;

        $events = request('events') ;
        /**
         * Validations des équipes si envoyés
         */
        foreach ($events as $index => $event) {
          if(isset($event['teams']) && isset($event['teams'][0]) && $event['teams'][0] != "*")
          {
            $eventIndex = 'events.' . $index . '.teams' ;
            request()->validate([
              $eventIndex           => 'required|Array',
              $eventIndex . '.*'    => 'integer|exists:teams,id'
            ]) ;
          }
        }


        foreach ($events as $event) {
            // on crée l'évènement
            $newEvent = Event::create($event) ;
            // on rajoute les teams associés
            if(isset($event['teams']))
            {
                if(isset($event['teams'][0]) && $event['teams'][0] == "*"){
                    $competitionInstance = CompetitionInstance::where('id',$event['competition_instance_id'])->first();
                    $newEvent->teams()->attach($competitionInstance->competition->teams->pluck('id')) ;
                }else{
                    $newEvent->teams()->attach($event['teams']) ;
                }
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Event $event)
    {
        $data = Event::with(['arbitration_area','division','stage','stade','competition_instance', 'eventSuspensions.sportsman'])
        ->where('id',$event->id)->first();
        return response()->json($data, 200);
    }
    public function getEventBySport($sport)
    {
        $data = Event::with(['arbitration_area','teams','competition_instance','division','stage','stade','eventSportsmen', 'eventSuspensions.sportsman' ,'swapSportsmen', 'competition_instance.competition.federation.sport'])
                    ->whereHas('competition_instance.competition.federation.sport', function($q) use($sport){
                      $q->where('id', $sport);
                    })
                    ->get();
        return response()->json($data, 200);
    }
    public function getEventByCompetition(Competition $competition)
    {
      $competitionInstances = $competition->competitionInstances ;
      $events = [] ;
      $nbCompetitionInstances = sizeof($competitionInstances) ;
      if($nbCompetitionInstances > 0){
        $competitionInstance = $competitionInstances[$nbCompetitionInstances - 1] ;
        $events = $competitionInstance->events->sortBy('start_date') ;
      }
      return response()->json($events, 200);
    }

    /**
     * Display All Event By CurentReferee
     */

     public function getEventByArbitrationArea()
     {
        $events = [] ;
        if(request()->has('arbitration_areas')){
            $events = Event::with(['arbitration_area','division','stage','stade','teams','competition_instance.competition.federation.sport','event_referees.referee', 'swapReferees'])
                            ->whereIn('arbitration_area_id',request('arbitration_areas'))
                            ->get();
        }
        return response()->json($events , 200);

     }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Event $event)
    {
        $data = $this->updatevalidator();
        $now = Carbon::now()->format('Y-m-d H:i:s');

        if (request('start_date') != $event->start_date) {
            $event->update([
                'start_date_updated' => Carbon::now()->addDay()->format('Y-m-d'),
                'last_start_date' => $event->start_date
            ]);
        }
        
        $result = $event->update($data);
        $this->storeImage($event);

        if(request()->has('teams')) {
          $event->teams()->sync(request('teams'));
        }

        if(request()->has('referee')) {
          $event->event_referees()->create([
            'referee_id' => request('referee')
          ]);
        }

        return response()->json($result, 200);
    }

    public function updatePriceDeviseByCompetitionInstance($competitionInstance){
        $result = Event::where('competition_instance_id',$competitionInstance)->update($this->updatevalidator());
        return response()->json($result, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Event $event)
    {
        $event->delete();
        return response()->json(null, 200);
    }

    public function destroyByCompetionInstance()
    {
        if(request()->has('competition_instance_id')){
            Event::where('competition_instance_id',request('competition_instance_id'))->delete();
            return response()->json('succes', 200);
        }else{
            return response()->json('Le paramètre competition_instance_id est manquant', 400);
        }
    }

    public function getByCompetionInstance($competition_instance_id)
    {
        $data = Event::with(['arbitration_area','division','stage','stade','teams','competition_instance', 'event_referees.referee', 'eventSuspensions.sportsman'])
                    ->where('competition_instance_id', $competition_instance_id)
                    ->get() ;

        return response()->json( $data, 200);
    }

    public function addCustomFieldsOnEvent(Request $request) {

      // Validate data
      $custom_fields = $this->customFieldsTeamEventValidator();
      $fields  = request('custom_fields');

      DB::transaction(function () use ($fields){
          foreach ($fields as $field => $field_value) {
            CustomFieldTeamEvent::create($field_value);
          }
      });

      return response()->json('success', Response::HTTP_OK);
    }

    // Pour respecter le principe de role, 
    // on pourrait utiliser le mecanisme de custom request de laravel 
    public function validator(){
        return request()->validate([
        'name' => 'nullable|string|max:255',
        'start_date' => 'required|date',
        'location' => 'nullable|string|max:255',
        'description' => 'nullable|string|max:255',
        'seniority_referee' => 'nullable|integer',
        'auto_referee' => 'required|boolean',
        'arbitration_area_id' => 'required|integer|exists:arbitration_areas,id',
        'end_date' => 'nullable|date',
        'division_id' => 'nullable|integer|exists:divisions,id',
        'stage_id' => 'required|integer|exists:stages,id',
        'picture' => 'nullable|image|max:5000',
        'info' => 'nullable|string|max:255',
        'step' => 'nullable|string|max:255',
        'stade_id' =>'nullable|integer|exists:stades,id',
        'competition_instance_id' =>'required|integer|exists:competition_instances,id',
        'price' => 'nullable|numeric',
        'devise_id'=>'nullable|integer|exists:devises,id',
        'nb_referee'=>'nullable|integer'
        ]);
    }
    public function updatevalidator(){
        return request()->validate([
            'name' => 'nullable|string|max:255',
            'start_date' => 'sometimes|date',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'seniority_referee' => 'nullable|integer',
            'auto_referee' => 'sometimes|boolean',
            'arbitration_area_id' => 'sometimes|integer|exists:arbitration_areas,id',
            'end_date' => 'nullable|date',
            'division_id' => 'nullable|integer|exists:divisions,id',
            'stage_id' => 'sometimes|integer|exists:stages,id',
            'picture' => 'nullable|image|max:5000',
            'info' => 'nullable|string|max:255',
            'step' => 'nullable|string|max:255',
            'stade_id' =>'nullable|integer|exists:stades,id',
            'competition_instance_id' =>'sometimes|integer|exists:competition_instances,id',
            'price' => 'nullable|numeric',
            'devise_id'=>'nullable|integer|exists:devises,id',
            'nb_referee'=>'nullable|integer'
            ]);
    }

    public function customFieldsTeamEventValidator() {
        return request()->validate([
            'custom_fields.*.event_id'                =>  'required|integer|exists:events,id',
            'custom_fields.*.team_id'                 =>  'required|integer|exists:teams,id',
            'custom_fields.*.value'                   =>  'nullable|string',
            'custom_fields.*.custom_field_team_id'    =>  'required|integer|exists:custom_field_teams,id',
            'custom_fields.*.date'                    =>  'nullable|date'
        ]);
    }

    //On pourrait aussi crée un service de stockage pour implémenter toute cette logique 
    // et l'injecter la où on veut l'utiliser
    private function storeImage(Event $event){
        if (request('picture')) {
            $name = request('picture')->store('logo_event','public');
            $name = str_replace('logo_event/', '', $name);
            $event->update([
                'picture' => $name
            ]);
        }
    }

    /**
     * Permet de renvoyer toutes les données de feuille de match de sportsmen par rapport à leurs équipe à un évenément
     * @param Event $event
     * @return array
     */
    public function getEventSportsmenMatchSheetResult(Event $event)
    {
        $event = $event->load(['cards', 'sportsmans', 'teams', 'customFieldSportsmanEvents']);

        foreach ($event['teams'] as $team) {
            $team->sportsmen = $event->sportsmans()->wherePivot('team_id', $team->id)->get();
        }

        unset($event['sportsmans']);

        return \response()->json($event, 200);
    }
}
