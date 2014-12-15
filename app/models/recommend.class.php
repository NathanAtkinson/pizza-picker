<?php

/**
 * User
 */
class Recommend extends CustomModel {

	//TODO implement validation using same structure as other validators
	protected function validators() {
        return [
        	'user_id' => [FILTER_VALIDATE_INT],
        	'recipe_id' => [FILTER_VALIDATE_INT],
            'user_ids' => [FILTER_CALLBACK,
            	['options' => function ($value) {
            		$valueArray = array_map('intval', explode(',', $value));
            		foreach ($valueArray as $check) {
            			if (!is_int($check)){
            				return false;
            			}
            		}

            		return $value;
            }]],
            'recipe_ids' => [FILTER_CALLBACK,
            	['options' => function ($value) {
                    $valueArray = array_map('intval', explode(',', $value));
                    foreach ($valueArray as $check) {
            			if (!is_int($check)){
            				return false;
            			}
            		}
            		return $value;
            }]],
            'topping_ids' => [FILTER_CALLBACK,
            	['options' => function ($value) {
            		$valueArray = array_map('intval', explode(',', $value));
            		foreach ($valueArray as $key => $check) {
            			if (!is_int($check)){
            				return false;
            			}
            		}
                    
            		return $value;
            }]]
        ];
    }

	/*protected function insert(){

	}*/

	//gets dislikes from a string of users
	public function getDislikes($input) {


        //input has to be an array...  with the key needed..otherwise "not all keys assigned"
        //error will be thrown.
		$cleanedInput = $this->cleanInput(
            ['user_ids'],
            $input, ['user_ids'] //option to not autoquote user_ids
        );

        // $cleanedInput = str_replace("'", "", $cleanedInput);
        if (is_string($cleanedInput)) return null;

		$getDislikes =<<<sql
        SELECT *
        FROM topping
        WHERE topping_id IN 
        (SELECT topping_id
		FROM user
		JOIN user_topping_dislike USING (user_id)
		WHERE user_id IN ({$cleanedInput['user_ids']}));
sql;
		return db::execute($getDislikes);
	}


	//finds recipes that use disliked toppings using topping ids
	public  function getExemptRecipes($input) {
		$cleanedInput = $this->cleanInput(
            ['topping_ids'],
            $input, ['topping_ids']
        );
        // there are single quotes after cleaning input...causing string of numbers to not work
        // $cleanedInput = str_replace("'", "", $cleanedInput);
        if (is_string($cleanedInput)) return null;

		$getExemptRecipes =<<<sql
        SELECT * 
        FROM pizza_recipe_topping 
        JOIN pizza_recipe USING (pizza_recipe_id) 
        JOIN topping USING (topping_id) 
        WHERE topping_id IN ({$cleanedInput['topping_ids']})
        GROUP BY pizza_recipe_id
sql;
		return db::execute($getExemptRecipes);
	}


	//gets suggestions ranked globally by all users, exempting recipes passed
	public function globalSuggestions($input) {

		$cleanedInput = $this->cleanInput(
            ['recipe_ids'],
            $input, ['recipe_ids']
        );
        // $cleanedInput = str_replace("'", "", $cleanedInput);
        if (is_string($cleanedInput)) return null;

		$globalSuggestions =<<<sql
		SELECT user_id, pizza_recipe_id, name, sum(vote_total) as total
        FROM pizza_recipe
        LEFT JOIN user_pizza_vote USING (pizza_recipe_id)
        LEFT JOIN `user` USING (user_id)
        WHERE pizza_recipe_id NOT IN ({$cleanedInput['recipe_ids']})
        GROUP BY pizza_recipe_id
        ORDER BY total DESC
sql;

		return db::execute($globalSuggestions);
	}


	//gets suggestions for user based on the users involved and exempting recipe's
	public function userSuggestions($input) {

		$cleanedInput = $this->cleanInput(
            ['recipe_ids', 'user_ids'],
            $input, ['recipe_ids', 'user_ids']
        );
        // $cleanedInput = str_replace("'", "", $cleanedInput);
        if (is_string($cleanedInput)) return null;

		$userSuggestions =<<<sql
		SELECT user_id, pizza_recipe_id, name, sum(vote_total) as total
        FROM pizza_recipe
        LEFT JOIN user_pizza_vote USING (pizza_recipe_id)
        LEFT JOIN `user` USING (user_id)
		where user_id IN ({$cleanedInput['user_ids']})
		and pizza_recipe_id NOT IN ({$cleanedInput['recipe_ids']})
        GROUP BY pizza_recipe_id
        ORDER BY total DESC
sql;
		return db::execute($userSuggestions);
	}

	
		
	//gets highest ranked suggestions (global) without exempting any recipes
	public function indifferentSuggestion() {

		$indifferentSuggestions =<<<sql
		SELECT user_id, pizza_recipe_id, name, sum(vote_total) as total
        FROM pizza_recipe
        LEFT JOIN user_pizza_vote USING (pizza_recipe_id)
        LEFT JOIN `user` USING (user_id)
        GROUP BY pizza_recipe_id
        ORDER BY total DESC
sql;

		return db::execute($indifferentSuggestions);
	}



	//gets past orders for a user based on user-ids
	public function getPastOrders($input) {

		$cleanedInput = $this->cleanInput(
            ['user_ids'],
            $input, ['user_ids']
        );

        // $cleanedInput = str_replace("'", "", $cleanedInput);
        if (is_string($cleanedInput)) return null;

		$getPastOrders =<<<sql
		SELECT pizza_recipe_id, sum(pizza_recipe_id) as total
		FROM past_order
		JOIN user USING (user_id)
		JOIN pizza_recipe USING (pizza_recipe_id)
		WHERE user_id IN ({$cleanedInput['user_ids']})
sql;

		return db::execute($getPastOrders);
	}



	// add to DB an order
	public function addOrder($input) {

		$cleanedInput = $this->cleanInput(
            ['recipe_id', 'user_id'],
            $input
        );

        // $cleanedInput = str_replace("'", "", $cleanedInput);
        if (is_string($cleanedInput)) return null;

		$addOrders =<<<sql
		INSERT INTO 
		past_order
		(`user_id`, `pizza_recipe_id`, `timestamp`) 
		VALUES ({$cleanedInput['user_id']}, {$cleanedInput['recipe_id']}, CURRENT_TIMESTAMP);
sql;

		db::execute($addOrders);
	}


	//when user selects "good" suggestion, upvotes to keep track
	public function upVote($input) {

		$cleanedInput = $this->cleanInput(
            ['recipe_id', 'user_id'],
            $input
        );

        // $cleanedInput = str_replace("'", "", $cleanedInput);
        if (is_string($cleanedInput)) return null;

		$upVote =<<<sql
		INSERT INTO user_pizza_vote
		(`user_id`, `pizza_recipe_id`, `vote_total`) 
		VALUES ({$cleanedInput['user_id']}, {$cleanedInput['recipe_id']}, 1)
		ON DUPLICATE KEY UPDATE
		vote_total=vote_total + 1
sql;

		db::execute($upVote);
	}



	//if not a good suggestion, down votes for user
	public function downVote($input) {

		$cleanedInput = $this->cleanInput(
            ['recipe_id', 'user_id'],
            $input
        );

        // $cleanedInput = str_replace("'", "", $cleanedInput);
        if (is_string($cleanedInput)) return null;

		$downVote =<<<sql
		INSERT INTO user_pizza_vote
		(`user_id`, `pizza_recipe_id`, `vote_total`) 
		VALUES ({$cleanedInput['user_id']}, {$cleanedInput['recipe_id']}, -1)
		ON DUPLICATE KEY UPDATE
		vote_total=vote_total -1
sql;

		db::execute($downVote);
	}
	




	//past query, to save for now
	/*$getRecs =<<<sql
        SELECT
            *
        FROM topping
        WHERE topping_id NOT IN 
        (SELECT 
        	topping_id
		FROM user
		JOIN user_topping_dislike USING (user_id)
		WHERE user_id IN ({$users}))
		LIMIT 5;
sql;

        return db::execute($getRecs);
	}*/

}