/// <reference types="cypress" />

import { personasFor, runApiGates } from "../../../../support/gate-runner";

personasFor("admin", "api").forEach((persona) => runApiGates(persona, "admin"));
