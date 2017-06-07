/**
 * alphaAPI
 * Original Author: chrisken
 * Original Url: http://www.cs.utexas.edu/users/chrisken/alphaapi.html
 *
 * Modified by dallen
 */
function alphaAPI(element, fadeInDelay, fadeOutDelay, startAlpha, stopAlpha, offsetTime, deltaAlpha)
{
	// {{{ properties

	this.element = typeof(element) == 'object' ? element : document.getElementById(element);
	this.fadeInDelay = fadeInDelay || 40;
	this.fadeOutDelay = fadeOutDelay || this.fadeInDelay;
	this.startAlpha = startAlpha;
	this.stopAlpha = stopAlpha;
	// make sure a filter exists so an error is not thrown
	if (typeof(this.element.filters) == 'object')
	{
		if (typeof(this.element.filters.alpha) == 'undefined')
		{
			this.element.style.filter += 'alpha(opacity=100)';
		}
	}

	this.offsetTime = (offsetTime || 0) * 1000;
	this.deltaAlpha = deltaAlpha || 10;
	this.timer = null;
	this.paused = false;
	this.started = false;
	this.cycle = false;
	this.command = function() {};

	// }}}
	// {{{ repeat()

	this.repeat = function(repeat)
	{
		this.cycle = repeat ? true : false;
	}

	// }}}
	// {{{ setAlphaBy()

	this.setAlphaBy = function(deltaAlpha)
	{
		this.setAlpha(this.getAlpha() + deltaAlpha);
	}
	
	// }}}
	// {{{ toggle()

	this.toggle = function()
	{
		if (!this.started)
		{
			this.start();
		}
		else if (this.paused)
		{
			this.unpause();
		}
		else
		{
			this.pause();
		}
	}
	
	// }}}
	// {{{ timeout()

	this.timeout = function(command, delay)
	{
		this.command = command;
		this.timer = setTimeout(command, delay);
	}
	
	// }}}
	// {{{ setAlpha()

	this.setAlpha = function(opacity)
	{
		if (typeof(this.element.filters) == 'object')
		{
			this.element.filters.alpha.opacity = opacity;
		}
		else if (this.element.style.setProperty)
		{
			this.element.style.setProperty('-moz-opacity', opacity / 100, '');
		}
	}	

	// }}}
	// {{{ getAlpha()

	this.getAlpha = function()
	{
		if (typeof(this.element.filters) == 'object')
		{
			return this.element.filters.alpha.opacity;
		}
		else if (this.element.style.getPropertyValue)
		{
			return this.element.style.getPropertyValue('-moz-opacity') * 100;
		}

		return 100;
	}
	
	// }}}
	// {{{ start()

	this.start = function()
	{
		this.started = true;
		this.setAlpha(this.startAlpha);
		// determine direction
		if (this.startAlpha > this.stopAlpha)
		{
			var instance = this;
			this.timeout(function() { instance.fadeOut(); }, this.offsetTime);
		}
		else
		{
			var instance = this;
			this.timeout(function() { instance.fadeIn(); }, this.offsetTime);
		}
	}
	
	// }}}
	// {{{ stop()

	this.stop = function()
	{
		this.started = false;
		this.setAlpha(this.stopAlpha);
		this.stopTimer();
		this.command = function() {};
	}
	
	// }}}
	// {{{ reset()

	this.reset = function()
	{
		this.started = false;
		this.setAlpha(this.startAlpha);
		this.stopTimer();
		this.command = function() {};
	}

	// }}}
	// {{{ pause()

	this.pause = function()
	{
		this.paused = true;
		this.stopTimer();
	}
	
	// }}}
	// {{{ unpause()

	this.unpause = function()
	{
		this.paused = false;
		if (!this.started)
		{ 
			this.start();
		}
		else
		{
			this.command(); 
		}
	}
	
	// }}}
	// {{{ stopTimer()

	this.stopTimer = function()
	{
		clearTimeout(this.timer);
		this.timer = null;
	}

	// }}}
	// {{{ fadeOut()

	this.fadeOut = function()
	{
		this.stopTimer();
		if (this.getAlpha() > this.stopAlpha)
		{
			this.setAlphaBy(-1 * this.deltaAlpha);
			var instance = this;
			this.timeout(function() { instance.fadeOut(); }, this.fadeOutDelay);
		}
		else
		{
			if (this.cycle)
			{
				var instance = this;
				this.timeout(function() { instance.fadeIn(); }, this.fadeInDelay);
			}
			else
			{
				this.started = false;
			}
		}
	}
	
	// }}}
	// {{{ fadeIn()

	this.fadeIn = function()
	{
		this.stopTimer();
		if (this.getAlpha() < this.startAlpha)
		{
			this.setAlphaBy(this.deltaAlpha);
			var instance = this;
			this.timeout(function() { instance.fadeIn(); }, this.fadeInDelay);
		}
		else
		{
			if (this.cycle)
			{
				var instance = this;
				this.timeout(function() { instance.fadeOut(); }, this.fadeOutDelay);
			}
			else
			{
				this.started = false;
			}
		}
	}
		
	return this;	

	// }}}
}
