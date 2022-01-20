--[[
	Registers methods that can be accessed through the Scribunto extension

	@since 2.2

	@licence GNU GPL v2+
	@author Alexander Mashin
]]

-- Variable instantiation
local external = {}

function external.setupInterface()

	-- Variable instantiation
	local php

	-- Interface setup
	external.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- Register library within the "mw.ext.externaldata" namespace
	mw = mw or {}
	mw.ext = mw.ext or {}

	-- Prepare parameters.
	local function prepareParams( parameters )
		local args = {}
		for key, value in pairs( parameters or {} ) do
			if tonumber( key ) then
				-- parameters without '=', like 'use xpath'.
				args[value] = true
			else
				args[key] = value
			end
		end
		return args
	end

	-- Export data retrieval functions from php (EDScribunto).
	for name, func in pairs( php ) do
		external[name] = function( parameters )
			local args = prepareParams( parameters )
			-- PHP exports an associative array with two elements.
			local pair = php[name]( args )
			return pair.values, pair.errors
		end
	end

	-- recommended.
	mw.ext.externalData = external
	package.loaded['mw.ext.externalData'] = external
	-- backward-compatible.
	mw.ext.externaldata = external
	package.loaded['mw.ext.externaldata'] = external

end

return external
