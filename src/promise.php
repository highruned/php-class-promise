<?

class Promise extends EventEmitter  {
	public $id;
	public $status;
	public $callback;
	public $jobs;
	public $finished;
	
	public function __construct() {
		parent::__construct();
	
		$this->jobs = array(
			'when' => array(),
			'then' => array()
		);
		
		$this->statuses = array(
			'when' => 0,
			'then' => 0
		);
	}
	
	public function remove_job($job) {
		$self = &$this;
	
		foreach($self->jobs as $i => $j1) {
			if($j1->id === $job->id) {
				array_splice($self->jobs, $i, 1);
				break;
			}
		}
	}
	
	public function when() {
		$self = &$this;
		
		$callbacks = func_get_args();
		
		if(!$callbacks) {
			$self->statuses['when'] = 1;
			
			$self->emit_once('when:complete');
			
			return $self;
		}
		
		foreach($callbacks as $callback) {
			$promise = new Promise();
			$promise->id = mt_rand(99999, 9999999);
			$promise->status = 0;
			
			$promise->callback = function() use(&$self, $promise, $callback) {
				$callback->__invoke($promise);
			};
			
			$promise->once('success', function() use(&$self, $promise) {
				$promise->status = 1;
			
				if($self->statuses['when'] === 1)
					return;
			
				$self->statuses['when'] = 1;
				
				foreach($self->jobs['when'] as $promise) {
					if(!$promise->is_complete())
						$self->statuses['when'] = 0;
				}
				
				if($self->statuses['when'] === 1) {
					$self->emit_once('when:complete');
				}
			});
			
			$promise->once('failure', function() use(&$self, $promise) {
				$promise->status = 2;
			
				if($self->statuses['when'] === 1)
					return;
			
				$self->statuses['when'] = 1;
				
				foreach($self->jobs['when'] as $promise) {
					if(!$promise->is_complete())
						$self->statuses['when'] = 0;
				}
				
				if($self->statuses['when'] === 1) {
					$self->emit_once('when:complete');
				}
			});
			
			//$promise->once('complete', function() use(&$self, $promise) {
			//	$self->remove_job($promise);
			//});
			
			$self->jobs['when'][] = $promise;
		}
			
		foreach($self->jobs['when'] as $i => $promise) {
			$promise->callback->__invoke($promise);
		}
		
		return $self;
	}
	
	public function is_complete() {
		if($this->status === 0)
			return false;
		
		foreach($this->jobs['when'] as $promise) {
			if(!$promise->is_complete())
				return false;
		}
		
		foreach($this->jobs['then'] as $promise) {
			if(!$promise->is_complete())
				return false;
		}
		
		return true;
	}
	
	public function then() {
		$self = &$this;
		
		$callbacks = func_get_args();
		
		if(!$callbacks) {
			$self->statuses['then'] = 1;
			
			$self->emit_once('then:complete');
			
			return $self;
		}
		
		foreach($callbacks as $callback) {
			$promise = new Promise();
			$promise->id = mt_rand(99999, 9999999);
			$promise->status = 0;
			
			$promise->callback = function() use(&$self, $promise, $callback) {
				$callback->__invoke($promise);
			};
			
			$promise->once('success', function() use(&$self, $promise) {
				$promise->status = 1;
				
				if($self->statuses['then'] === 1)
					return;
			
				$self->statuses['then'] = 1;
				
				foreach($self->jobs['then'] as $promise) {
					if(!$promise->is_complete())
						$self->statuses['then'] = 0;
				}
				
				if($self->statuses['then'] === 1) {
					$self->emit_once('then:complete');
				}
			});
				
			$self->jobs['then'][] = $promise;
		}
			
		foreach($self->jobs['then'] as $i => $promise) {
			$promise->callback->__invoke($promise);
		}
		
		return $self;
	}
	
	public function parallel() {
		$self = &$this;
		
		$callbacks = func_get_args();
		
		if(!$callbacks) {
			$self->statuses['when'] = 1;
			$self->statuses['then'] = 1;
			
			$self->emit_once('success');
			
			return $self;
		}
		
		call_user_func_array(array($self, 'when'), $callbacks);
		
		$self->then(function($success) use(&$self)  {
			$success->emit_once('success');
		});
	}
	
	public function run() {
		$self = &$this;
	
		foreach($self->jobs['when'] as $i => $promise) {
			$promise->callback->__invoke($promise);
		}
		
		foreach($self->jobs['then'] as $i => $promise) {
			$promise->callback->__invoke($promise);
		}
	}
}