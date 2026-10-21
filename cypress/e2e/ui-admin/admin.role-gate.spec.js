/// <reference types="cypress" />

import { personasFor, runPageGates } from "../../support/gate-runner";

personasFor("admin", "page").forEach((persona) => runPageGates(persona, "admin"));
