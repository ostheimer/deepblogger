<?php

/**
 * Registriert alle Aktionen und Filter für das Plugin
 */
class DeepBlogger_Loader {

    /**
     * Das Array der Aktionen, die beim WordPress registriert werden
     */
    protected $actions;

    /**
     * Das Array der Filter, die beim WordPress registriert werden
     */
    protected $filters;

    /**
     * Initialisiert die Collections, die für die Hooks verwendet werden
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Fügt eine neue Aktion zum Array der zu registrierenden Aktionen hinzu
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Fügt einen neuen Filter zum Array der zu registrierenden Filter hinzu
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Hilfsfunktion, die verwendet wird, um eine Aktion/einen Filter in die
     * entsprechenden Arrays einzufügen
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Registriert die Filter und Aktionen bei WordPress
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
} 