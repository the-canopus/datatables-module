<?php

namespace Modules\Datatables\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;
use Modules\Datatables\Exceptions\TransformerNotImplementedException;
use Nwidart\Modules\Module;
use ReflectionClass;
use ReflectionException;

class DatatablesController extends Controller
{

    /**
     * @var ReflectionClass
     */
    protected $reflection;


    /**
     * Qualified Transformer Class Full Name
     *
     * @var string
     */
    protected $transformer;

    /**
     * Datatable Module Name
     *
     * @var string
     */
    protected $module;

    /**
     *  perform datatables response for datatables requests
     *
     * @param  Request  $request
     * @param  mixed    $module
     * @param  mixed    $transformer
     *
     * @throws ReflectionException
     * @throws TransformerNotImplementedException
     * @return JsonResponse
     *
     */
    public function __invoke(Request $request, $module, $transformer)
    {
        $this->setVariables($module, $transformer);
        $this->qualifyRequest();
        $this->qualifyModule();
        $this->qualifyTransformer();
        $this->authorize();


        return datatables()->setTransformer($this->getReflectionClass()->newInstance())->toJson();
    }

    /**
     * Set class properties
     *
     * @param  mixed  $module
     * @param  mixed  $transformer
     *
     * @return void
     */
    protected function setVariables($module, $transformer)
    {
        $this->module = $module;
        $this->transformer = $this->getTransformerClassFullName($module, $transformer);
    }

    /**
     * Return Class Qualified Class name with namespace
     *
     * @param  mixed  $module
     * @param  mixed  $transformer
     *
     * @return string
     */
    protected function getTransformerClassFullName($module, $transformer)
    {
        return "\\Modules\\".Str::camel($module)."\\Transformers\\".Str::camel($transformer.'Transformer');
    }

    /**
     * Qualify if request is performed via ajax
     *
     * @return void
     */
    protected function qualifyRequest()
    {
        abort_unless(\request()->ajax(), Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * qualify if module is present and enable before performing any process on request
     *
     * @return void
     */
    protected function qualifyModule()
    {
        abort_unless($module = Module::find($this->module), Response::HTTP_NOT_FOUND);
        abort_unless($module->isEnabled(), Response::HTTP_BAD_REQUEST, "Module `$module` is Disabled!");
    }

    /**
     *qualify transformer is valid
     *
     * @throws ReflectionException
     * @throws TransformerNotImplementedException
     */
    protected function qualifyTransformer()
    {
        abort_unless(class_exists($this->transformer), Response::HTTP_NOT_FOUND);

        $reflection = $this->getReflectionClass();
        if (!$reflection->isSubclassOf($abstract = TransformerAbstract::class)) {
            throw new TransformerNotImplementedException("Transformer {$this->transformer} Should Extended from {$abstract}!");
        }
    }

    /**
     * return a reflection of transformer class
     *
     * @throws ReflectionException
     *
     */
    protected function getReflectionClass()
    {
        if ($this->reflection === null) {
            $this->reflection = new ReflectionClass($this->transformer);
        }
        return $this->reflection;
    }

    /**
     * authorize user can perform this request or not
     *
     * @throws ReflectionException
     * @return void
     */
    protected function authorize()
    {
        $reflection = $this->getReflectionClass();
        if ($reflection->hasMethod('authorize')) {
            abort_unless($reflection->newInstanceWithoutConstructor()->authorize(), Response::HTTP_FORBIDDEN);
        }
    }
}
